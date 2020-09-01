<?php

class DatabaseApi{

  public function connect(){

    $mysqli = null;
    if ($mysqli!=null)
      return $mysqli;

    $host = 'mysql';
    $user = getenv('MYSQL_USER');
    $pass = getenv('MYSQL_PASSWORD');
    $database = getenv('MYSQL_DATABASE');

    $mysqli = new mysqli($host, $user, $pass, $database);
    if (mysqli_connect_errno()) {
        printf("Connect failed: %s\n", mysqli_connect_error());
        printf("Errno: \n%s\n", $mysqli->connect_errno);
        printf("Error: \n%s\n", $mysqli->connect_error);
        return null;
    }
    return $mysqli;
  }

  public function insertSearch($base_currency, $start_date, $end_date){

    $mysqli = $this->connect();
    if($mysqli==null)
      return false;

    $sql = "INSERT IGNORE INTO search (base_currency, start_date, end_date, created) VALUES" .
            "('" . $base_currency . "','" .  $start_date . "','" . $end_date . "', now())";

    if (!$result = $mysqli->query($sql)) {
        printf("Query: %s\n", $sql);
        printf("Errno: %s\n", $mysqli->errno);
        printf("Error: %s\n", $mysqli->error);
        return false;
    }

    $mysqli->close();
    return true;
  }

  public function deleteSearch($created){

    $mysqli = $this->connect();
    if($mysqli==null)
      return false;

    // read the search record
    $search = $this->getSearch($created);
    if($search!=null){
      // delete the rates associated with the search
      if($this->deleteExchangeRates($search[0], $search[1], $search[2])){
        // then delete the search record
        $sql = "DELETE FROM search WHERE created = '" . $created . "'";
        if (!$result = $mysqli->query($sql)) {
            printf("Query: %s\n", $sql);
            printf("Errno: %s\n", $mysqli->errno);
            printf("Error: %s\n", $mysqli->error);
            return false;
        }
      }
    }
    $mysqli->close();
    return true;
  }

  public function deleteExchangeRates($base_currency, $start_date, $end_date){

    $mysqli = $this->connect();
    if($mysqli==null)
      return false;

    // then delete the search record
    $sql = "DELETE FROM exchange_rate WHERE base_currency='" . $base_currency . "' AND exchange_date>='" . $start_date . "' AND exchange_date<='" . $end_date . "'";

    if (!$result = $mysqli->query($sql)) {
        printf("Query: %s\n", $sql);
        printf("Errno: %s\n", $mysqli->errno);
        printf("Error: %s\n", $mysqli->error);
        return false;
    }
    $mysqli->close();
    return true;
  }

  public function insertRate($base_currency, $target_currency, $exchange_rate, $exchange_date){

    $mysqli = $this->connect();
    if($mysqli==null)
      return false;

    $sql = "INSERT IGNORE INTO exchange_rate (base_currency, target_currency, exchange_rate, exchange_date) VALUES" .
            "('" . $base_currency . "','" .  $target_currency . "'," . $exchange_rate . ",'" . $exchange_date ."')";

    if (!$result = $mysqli->query($sql)) {
        printf("Query: %s\n", $sql);
        printf("Errno: %s\n", $mysqli->errno);
        printf("Error: %s\n", $mysqli->error);
        return false;
    }

    $mysqli->close();
    return true;
  }

  public function getSearch($created){

    $mysqli = $this->connect();
    if($mysqli==null)
      return null;

    $sql = "SELECT base_currency, start_date, end_date, created from search WHERE created='" . $created .  "'";

    if (!$result = $mysqli->query($sql)) {
        printf("Query: %s\n", $sql);
        printf("Errno: %s\n", $mysqli->errno);
        printf("Error: %s\n", $mysqli->error);
        return null;
    }
    if ($result->num_rows != 1) {
      $mysqli->close();
      return null;
    }

    $searchItem = $result->fetch_assoc();
    $search = array($searchItem['base_currency'], $searchItem['start_date'], $searchItem['end_date'], $searchItem['created']);

    $result->free();
    $mysqli->close();

    return $search;
  }

  public function getSearches($max){

    $mysqli = $this->connect();
    if($mysqli==null)
      return null;

    $sql = "SELECT base_currency, start_date, end_date, created from search ORDER by created desc limit " . $max; # just in case

    if (!$result = $mysqli->query($sql)) {
        printf("Query: %s\n", $sql);
        printf("Errno: %s\n", $mysqli->errno);
        printf("Error: %s\n", $mysqli->error);
        return null;
    }
    if ($result->num_rows === 0) {
      $mysqli->close();
      return null;
    }

    $resultSearches = array();
    while ($searchItem = $result->fetch_assoc()) {
        // echo $rates['base_currency'] . ' ' . $rates['target_currency'] . ' ' . $rates['exchange_rate'] . ' ' . $rates['exchange_date'] . '<br>';
        $search = array($searchItem['base_currency'], $searchItem['start_date'], $searchItem['end_date'], $searchItem['created']);
        array_push($resultSearches, $search);
    }
    $result->free();
    $mysqli->close();
    return $resultSearches;
  }

  public function checkSearchRange($baseCurrency, $startdate, $enddate){

    $mysqli = $this->connect();
    if($mysqli==null)
      return -1;

    $sql = "SELECT COUNT(created) as count FROM search WHERE base_currency = '" . $baseCurrency . "' AND start_date <= '" . $startdate . "' AND end_date >= '" . $enddate . "'";

    if (!$result = $mysqli->query($sql)) {
        printf("Query: %s\n", $sql);
        printf("Errno: %s\n", $mysqli->errno);
        printf("Error: %s\n", $mysqli->error);
        return false;
    }
    if ($result->num_rows != 1) {
      $mysqli->close();
      return -1;
    }
    $count = $result->fetch_assoc();
    $result->free();
    $mysqli->close();
    return $count['count'];
  }

  public function getRatesByCode($baseCurrency, $startdate, $enddate){

    $mysqli = $this->connect();
    if($mysqli==null)
      return null;

    $sql = "SELECT target_currency, exchange_rate, exchange_date from exchange_rate " .
            "WHERE base_currency='" . $baseCurrency . "' AND exchange_date>='" . $startdate . "' AND exchange_date<='" . $enddate . "' ORDER BY target_currency ASC, exchange_date desc";

    if (!$result = $mysqli->query($sql)) {
        printf("Query: %s\n", $sql);
        printf("Errno: %s\n", $mysqli->errno);
        printf("Error: %s\n", $mysqli->error);
        return null;
    }
    if ($result->num_rows === 0) {
      $mysqli->close();
      echo "getRatesByCode - exit";
      return null;
    }

    $currency_codes = array();
    $currency_dates = array();
    while ($rates = $result->fetch_assoc()) {
        if($rates['exchange_date']!=""){
          $currency_dates[$rates['exchange_date']] = $rates['exchange_rate'];
        }
        $currency_codes[$rates['target_currency']] = $currency_dates;
    }
    $result->free();
    $mysqli->close();

    return $currency_codes;
  }
}

?>
