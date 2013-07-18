<?php

// TODO for development, create link with FirePHP Console
// dirty hack here to get this to work
require_once("../FirePHPCore/FirePHP.class.php");

/**
 * A general class to access the various DBs and fetch some data from them, for the Air U of T application.
 */
class AirUofT_Model extends CI_Model {
	/**
	 * An associative array mapping campus 'short names' to their IDs in the DB tables.
	 */
	private $campuses = array();
	
	function __construct () {
		parent::__construct();
		
		// TODO for development, create link with FirePHP Console
		$this->logger = FirePHP::getInstance(true);
		
		// to start with, have a mapping of campus names to campus IDs
		foreach (array("UTSG" => "St. George", "UTM" => "Mississauga") as $campus => $campusFullName) {
			$q = "SELECT id FROM campus WHERE name='$campusFullName';";
			$query = $this->db->query($q);
			$id = $query->row()->id;
			$this->campuses[$campus] = intval($id);
		}
	}
	
	/**
	 * Return an array, which is formed by joining the key-value pairs of $array using $glue
	 */
	function a_array_join ($glue, $array) {
		$a2 = array();
		
		foreach($array as $k => $v) {
			$a2[] = $k . "=" . $v;
		}
		
		return $a2;
	}
	
	/**
	 * Construct a query using the given array.
	 * @param $table The table to query
	 * @param $array The associative array mapping columns to values
	 * @param $columns The columns to retrieve from the table. If none specified, return all of them.
	 */
	function construct_query ($table, $array, $columns=null) {
		if ($columns !== null) {
			$colString = "*";
		} else {
			$colString = join(", ", $columns);
		}
		
		$qString = join(" AND ", $this->a_array_join($array));
		
		return "SELECT $colString FROM $table WHERE $qString";
	}
	
	/**
	 * Construct an insert SQL query using the given associative array of column-value pairs.
	 * WARNING: modifies array that it is given
	 * @param $table The table to insert into
	 * @param $arr The associative array
	 */
	function construct_insert ($table, $arr) {
		// quote the strings
		foreach ($arr as $key=>&$value) {
			if (! is_numeric($value)) {
				$value = "'$value'";
			}
		}
		
		$colString = join(",", array_keys($arr));
		$valString = join(",", array_values($arr));
		
		return "INSERT INTO $table ($colString) VALUES ($valString);";
	}
	
	/**
	 * Return an associative array of times mapping to flight IDs that fit the search criteria.
	 * @param $from Departure campus (one of UTM, UTSG)
	 * @param $to Destination campus (one of UTM, UTSG)
	 * @param $date Departure date (in the format yyyy-mm-dd)
	 */
	function get_available_flights ($from, $to, $date) {
		// time to get the campus IDs
		
		$campusFrom = $this->campuses[$from];
		$campusTo = $this->campuses[$to];
		
		$q = "SELECT timetable.time AS departureTime, flight.id AS flightID
		FROM timetable INNER JOIN flight ON timetable.id=flight.timetable_id
		WHERE timetable.leavingfrom=$campusFrom AND timetable.goingto=$campusTo AND flight.date='$date' AND flight.available=1;";
		
		$query = $this->db->query($q);
		$times = array();
		
		if ($query->num_rows() == 0) {
			$this->logger->log($q, "No result set on fetching flights with this query: ");
		} else {
			foreach ($query->result() as $row) {
				$times[$row->departureTime] = $row->flightID;
			}
		}
		
		return $times;
	}
	
	/**
	 * Fill the flight table for the next 14 days with the relevant information.
	 * Return true iff successful.
	 */
	function fill_flights() {
		$this->db->trans_start();
		$date = new DateTime(date("Y-m-d"));
				
		foreach (range (1, 15) as $i) {
			date_add($date, date_interval_create_from_date_string("1 day"));
			
			foreach (range (1, 8) as $j) {
				$q = $this->construct_insert("flight", array("timetable_id" => $j, "date" => date_format($date, "Y-m-d"), "available" => 1));
				$this->db->query($q);
			}
		}
		
		$this->db->trans_complete();
		$result = $this->db->trans_status();
		
		if (! $result) {
			$this->logger->log("Populating flight table failed" ,"");
		}
		
		return $result;
	}
	
	/**
	 * Return a list of seat numbers that are available for this flight.
	 * Default is array(0, 1, 2)
	 */
	function get_available_seats ($flightID) {
		$q = "SELECT seat FROM ticket WHERE flight_id=$flightID;";
		$query = $this->db->query($q);
		$goodSeats = range(0, 2);
		
		foreach($query->result() as $row) {
			if (array_key_exists($row->seat, $goodSeats)) {
				unset($goodSeats[$row->seat]);
			}
		}
		
		return $goodSeats;
	}
	
	/**
	 * Create a brand-new ticket from the passed data.
	 * Return true iff successfully inserted item.
	 */
	function create_ticket ($fName, $lName, $ccNum, $ccExpDate, $flightID, $seatNum) {
		$data = array(
			"first" => $fName,
			"last" => $lName,
			"creditcardnumber" => $ccNum,
			"creditcardexpiration" => $ccExpDate,
			"flight_id" => $flightID,
			"seat" => $seatNum
		);
			
		return $this->$db->insert($data);
	}
	
	/**
	 * Return an array of tickets. Each ticket is a Ticket object (see the documentation in model/ticket.php)
	 */
	function get_tickets () {
		$this->load->model("ticket");
		
		$q = "SELECT ticket.first AS fName, 
		ticket.last AS lName,
		ticket.creditcardnumber AS ccNum,
		ticket.creditcardexpiration AS ccExpDate,
		ticket.seat AS seatNum,
		flight.date AS flightDate
		FROM ticket INNER JOIN flight ON ticket.flight_id=flight.id;";
		
		$query = $this->db->query($q);
		$tickets = array();
		
		if ($query->num_rows() == 0) {
			$this->logger->log($q, "No result set on fetching tickets with this query: ");
		} else {
			foreach ($query->result() as $row) {
				$f = new Ticket($row->flightDate, $row->seatNum, $row->fName, $row->lName, $row->ccNum, $row->ccExpDate);
				$tickets[] = $f;
			}
		}
		
		return $tickets;
	}

	/**
	 * Delete everything in flights and tickets tables.
	 * Return True iff successful.
	 */	
	function delete_flights_and_tickets() {
		$this->db->trans_start();
		
		foreach (array("flight", "ticket") as $table) {
			$q = "DELETE FROM $table WHERE 1=1";
			$this->db->query($q);
		}
		
		$this->db->trans_complete();
		$result = $this->db->trans_status();
		
		if (! $result) {
			$this->logger->log("Deleting flight and ticket data failed" ,"");
		}
		
		return $result;
	}
}
?>