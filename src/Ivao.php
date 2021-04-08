<?php
/**
 * Simple IVAO class to download the whazzup data.
 *
 * Information on the IVAO API: https://wiki.ivao.aero/en/home/devops/api/information
 * Information on whazzup.txt format: https://wiki.ivao.aero/en/home/devops/api/whazuup/file-format
 * Caution: The table on the wiki website containing the order of the fields in _crap_ and not to be trusted!
 *
 * @author Christian Haensel <chris@haensel.pro>
 * @copyright Copyright (c) 2021, Christian Hänsel
 * @version 0.1
 */

namespace chrishaensel;


use stdClass;


class Ivao {

	// We need to set an app name according to IVAO R&R, which will be passed to IVAO when downloading the data
	private $app_name;

	// The URL of the IVAO status.txt file
	private $status_txt_file_url = "https://www.ivao.aero/whazzup/status.txt";

	// name of the project's temporary directory
	private $tmp_dir = "tmp";
	private $ds = DIRECTORY_SEPARATOR;

	// The names of the files on the local file system - aka the download targets
	private $ivao_status_file_name = "ivao_status.txt";
	private $ivao_whazzup_file_name = "ivao_whazzup.txt";
	private $clean_whazzup_file_name = "clean_whazzup.txt";
	private $json_whazzup_file_name = "whazzup.json";

	// Maximum age of the status.txt file. After this period, it will be downloaded again.
	private $status_txt_max_age_h = 24; // In hours

	// According to IVAO R & R, we may only download whazzup data every 5 minutes
	private $whazzup_txt_min_age_m = 5; // In Minutes

	// Don't change this:
	private $local_status_txt_filename;
	private $local_whazzup_txt_filename;
	private $local_clean_whazzup_txt_filename;
	private $create_json = false;
	// End of "don't change this"  :)


	/**
	 * Ivao constructor.
	 * Needs the param $app_name accoring to IVAO rules and regulations:
	 * https://wiki.ivao.aero/en/home/devops/api/whazuup/how-to-retrieve
	 *
	 * @param string|null $app_name The name of your app. Will be passed as user agent string
	 *
	 * @throws \Exception
	 */
	public function __construct( string $app_name = null, array $options = [] ) {
		if ( is_null( $app_name ) ) {
			throw new \Exception( "App name must be given according to IVAO R&R. Aborting." );
		}

		if ( count( $options ) > 0 ) {
			if ( isset( $options['create_json'] ) ) {
				$this->create_json = intval( $options['create_json'] ) == 1;
			}
		}

		$this->local_status_txt_filename        = "{$this->tmp_dir}{$this->ds}{$this->ivao_status_file_name}";
		$this->local_whazzup_txt_filename       = "{$this->tmp_dir}{$this->ds}{$this->ivao_whazzup_file_name}";
		$this->local_clean_whazzup_txt_filename = "{$this->tmp_dir}{$this->ds}{$this->clean_whazzup_file_name}";
		$this->local_json_whazzup_txt_filename  = "{$this->tmp_dir}{$this->ds}{$this->json_whazzup_file_name}";

		$this->app_name = trim( $app_name );
		$this->checkStatusTxtFreshness();

	}

	/**
	 * This method will download the current data from IVAO if we meet the
	 * criteria of IVAO's rules and regulations.
	 *
	 * @param null $target_file_path The file path where to store the whazzup file
	 *
	 * @throws \Exception
	 */
	public function downloadIvaoWhazzupData( $target_file_path = null ) {
		if ( ! is_null( $target_file_path ) ) {
			$this->local_whazzup_txt_filename = trim( $target_file_path );
		}

		$this->downloadWhazzupData();
	}


	/**
	 * get the whole data of the whazzup.txt as JSON
	 *
	 * @return false|string
	 */
	public function getJson() {
		if ( file_exists( $this->local_clean_whazzup_txt_filename ) ) {
			$csv   = file_get_contents( $this->local_clean_whazzup_txt_filename );
			$array = array_map( "str_getcsv", explode( "\n", $csv ) );
			$json  = json_encode( $array );

			return $json;
		}

		return null;
	}

	/**
	 * Downloading the status.txt if needed
	 */
	private function checkStatusTxtFreshness() {
		$needDownload = false;
		if ( ! file_exists( $this->local_status_txt_filename ) ) {
			// Download the status.txt as we don't have it locally
			$needDownload = true;
		} else {
			// Check whether our local status.txt file is older than 24 hours. If so, download a new one
			$status_txt_filemtime = filemtime( $this->local_status_txt_filename );
			if ( $this->getFileAge( $status_txt_filemtime, "h" ) > $this->status_txt_max_age_h ?? 24 ) {
				$needDownload = true;
			}
		}

		if ( $needDownload ) {
			$this->downloadStatusTxtFile();
		}

	}

	/**
	 * Have the readable rating for the user based on client_type ATC or PILOT
	 *
	 * @param int $rating_id
	 * @param string $type
	 *
	 * @return string
	 */
	private function decodeRating( int $rating_id, $type = "PILOT" ) {

		if ( $rating_id > 0 ) {
			$rating_id = $rating_id - 1;
		}
		switch ( $type ) {
			case "ATC":
				$arr = [
					"Observer",
					"ATC Applicant - AS1",
					"ATC Trainee - AS2",
					"Advanced ATC Trainee - AS3",
					"Aerodrome Controller - ADC",
					"Approach Controller - APC",
					"Center Controller - ACC",
					"Senior Controller - SEC",
					"Senior ATC Instructor - SAI",
					"Chief ATC Instructor - CAI",
				];

				return $arr[ $rating_id ];
				break;

			case "PILOT":
			default:
				$arr = [
					"Observer",
					"Basic Flight Student (FS1)",
					"Flight Student (FS2)",
					"Advanced Flight Student (FS3)",
					"Private Pilot (PP)",
					"Senior Private Pilot (SPP)",
					"Commercial Pilot (CP)",
					"Airline Transport Pilot (ATP)",
					"Senior Flight Instructor (SFI)",
					"Chief Flight Instructor (CFI)",
				];

				return $arr[ $rating_id ];
				break;
		}


	}

	/**
	 * Downloading stuff from IVAO while passing the app name user user agent string.
	 *
	 * @param string $url
	 *
	 * @return bool|string
	 */
	private function curlDownloadFile( string $url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_USERAGENT, $this->app_name );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		$output = curl_exec( $ch );
		curl_close( $ch );

		return $output;
	}

	/**
	 * Download the statuts.txt from IVAO and saving it to the temporary directory.
	 * @return bool
	 * @throws \Exception
	 */
	private function downloadStatusTxtFile() {
		$status_txt = $this->curlDownloadFile( $this->status_txt_file_url );
		try {
			file_put_contents( "{$this->tmp_dir}{$this->ds}{$this->ivao_status_file_name}", $status_txt );

			return true;
		} catch ( \Exception $e ) {
			throw new \Exception( "Download of status.txt failed: {$e->getMessage()}" );
		}
	}

	/**
	 * Downloading the whazzup data from IVAO - taking into account the R & R
	 *
	 * @throws \Exception
	 */
	private function downloadWhazzupData() {
		$downloadData = false;
		if ( ! file_exists( $this->local_whazzup_txt_filename ) ) {
			$downloadData = true;
		} else {
			$whazzup_filemtime = filemtime( $this->local_whazzup_txt_filename );
			if ( $this->getFileAge( $whazzup_filemtime, "m" ) >= $this->whazzup_txt_min_age_m ) {
				$downloadData = true;
			}
		}
		if ( $downloadData ) {

			// we are reading the local status.txt to get the gzurls to use
			if ( ! file_exists( $this->local_status_txt_filename ) ) {
				throw new \Exception( "Local file tatus.txt is not available" );
			}
			$urls   = parse_ini_file( $this->local_status_txt_filename );
			$gzurls = [];
			if ( isset( $urls["gzurl0"] ) ) {
				$gzurls[] = $urls["gzurl0"];
			}
			if ( isset( $urls["gzurl1"] ) ) {
				$gzurls[] = $urls["gzurl1"];
			}


			// Can we use GZ? If not, we need to get the uncompressed variant
			if ( count( $gzurls ) > 0 ) {
				// Get a random gzurl, just in case we have more than one.
				$use_gz_url = $gzurls[ rand( 0, count( $gzurls ) - 1 ) ];

				// Download and gzread and stuff and write to file
				file_put_contents( "{$this->tmp_dir}{$this->ds}whazzup.txt.gz",
					$this->curlDownloadFile( $use_gz_url ) );
				$fp       = gzopen( "{$this->tmp_dir}{$this->ds}whazzup.txt.gz", "r" );
				$contents = gzread( $fp, 1000000 );
				file_put_contents( $this->local_whazzup_txt_filename, $contents );
				gzclose( $fp );
			} else {
				// We need to use uncompressed whazzup because there is no gzurl in the status.txt
				$whazzup_url = $urls['url0'];
				file_put_contents( "{$this->local_whazzup_txt_filename}", $this->curlDownloadFile( $whazzup_url ) );

			}

			// We will save the raw pilot and ATC data into a different file to tinker with it later on
			$data       = file( $this->local_whazzup_txt_filename );
			$clean_data = null;
			$json_arr   = [];
			foreach ( $data as $line ) {
				$line = utf8_encode( $line );
				if ( strpos( $line, "ATC" ) || strpos( $line, "PILOT" ) ) {
					$clean_data .= $line;

					if ( $this->create_json ) {
						// we only do this if we want to create JSON
						$items         = explode( ":", $line );
						$obj           = new stdClass();
						$obj->callsign = $items[0];

						$obj->user         = new stdClass();
						$obj->user->vid    = $items[1];
						$obj->user->name   = $items[2];
						$obj->user->rating = intval( $items[41] ); // The user's rating
						// Sometimes, we're getting the rating as 0... then we can not subtract the 1
						// Until I figured that out, we will just suppress the warning.
						$obj->user->rating_decoded = @$this->decodeRating( $obj->user->rating, $items[3] );

						$obj->client_type = $items[3];
						$obj->freq        = $items[4];

						$obj->position            = new stdClass();
						$obj->position->latitude  = $items[5];
						$obj->position->longitude = $items[6];
						$obj->position->altitude  = $items[7];

						$obj->flight_data              = new stdClass();
						$obj->flight_data->groundspeed = $items[8];
						$obj->flight_data->heading     = $items[45];
						$obj->flight_data->on_ground   = $items[46];

						$obj->flightplan                       = new stdClass();
						$obj->flightplan->aircraft             = $items[9];
						$obj->flightplan->cruising_speed       = $items[10];
						$obj->flightplan->origin               = $items[11];
						$obj->flightplan->cruising_level       = $items[12];
						$obj->flightplan->destination          = $items[13];
						$obj->server                           = $items[14];
						$obj->protocol                         = $items[15];
						$obj->combined_rating                  = $items[16]; // Deprecated
						$obj->transponder_code                 = $items[17];
						$obj->facility_type                    = $items[18];
						$obj->visual_range                     = $items[19];
						$obj->flightplan->revision             = $items[20];
						$obj->flightplan->flight_rules         = $items[21];
						$obj->flightplan->dep_time             = $items[22];
						$obj->flightplan->actual_dep_time      = $items[23];
						$obj->flightplan->eet_hours            = $items[24];
						$obj->flightplan->eet_minutes          = $items[25];
						$obj->flightplan->endurance_hours      = $items[26];
						$obj->flightplan->endurance_minutes    = $items[27];
						$obj->flightplan->alternate_aerodrome  = $items[28];
						$obj->flightplan->remarks              = $items[29];
						$obj->flightplan->route                = $items[30];
						$obj->flightplan->alternate_aerodrome2 = $items[42];
						$obj->flightplan->type_of_flight       = $items[43];
						$obj->flightplan->persons_on_board     = $items[44];

						$obj->unused1             = $items[31];
						$obj->unused2             = $items[32];
						$obj->ATIS                = $items[33];
						$obj->ATIS_time           = $items[34];
						$obj->connection_time     = $items[37];
						$obj->connection_duration = $this->onlineDuration( $obj->connection_time );

						$obj->software          = new stdClass();
						$obj->software->name    = $items[38];
						$obj->software->version = $items[39];

						$obj->plane = $items[9];

						$json_arr[ $obj->client_type ][] = $obj;
					}

				}
			}
			if ( file_exists( $this->local_clean_whazzup_txt_filename ) ) {
				unlink( $this->local_clean_whazzup_txt_filename );
			}
			if ( $this->create_json ) {
				$json = json_encode( $json_arr );
				file_put_contents( $this->local_json_whazzup_txt_filename, $json );
			}

			file_put_contents( $this->local_clean_whazzup_txt_filename, $clean_data );


		}
	}

	/**
	 * Get the age of a specified file in usable format - Days, hours, minutes - whatever you need.
	 *
	 * @param string $filemtime The filemtime of the file you want to check
	 * @param string $format // The age in one of the formats: d = days, h = hours, I = seconds, ...
	 *
	 * @return mixed
	 */
	public function getFileAge( string $filemtime, string $format = "h" ) {
		$file_datetime = date( "Y/m/d H:i:s", $filemtime );


		$dt_file  = \DateTime::createFromFormat( "Y/m/d H:i:s", $file_datetime );
		$dt_now   = new \DateTime();
		$interval = $dt_now->diff( $dt_file );

		return $interval->$format;
	}

	public function onlineDuration( string $connection_time = null ) {
		if ( preg_match( "/[!\D]/", $connection_time ) ) {
			// Do we have any stuff other than digits? Then this is not the connection time. RUN!
			return null;
		}
		// Sample: 20210408110822 = 2021 04 08 11 08 22

		$dt_connect = \DateTime::createFromFormat( "YmdHis", $connection_time );
		$dt_now     = new \DateTime();
		$interval   = $dt_now->diff( $dt_connect );

		return $interval->format( '%h:%i:%s' );
	}

}
