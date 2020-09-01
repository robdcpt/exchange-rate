<?php

// a wrapper class for calling https://exchangeratesapi.io/ API
class ExchangeApi{

  const EXCHANGE_URL = 'https://api.exchangeratesapi.io';

  public function getCurrencyCodes(){

    $method = "GET";
    $url = self::EXCHANGE_URL . "/latest";
    $result = $this->callAPI($method, $url);
    if($result==null)
      return array(); // no results return empty array

    $resultObj = json_decode($result);
    $currencies = array();
    array_push($currencies, $resultObj->base);
    foreach($resultObj->rates as $key => $value){
      array_push($currencies, $key);
      sort($currencies);
    }
    return $currencies;
  }

  public function getRatesByDate($baseCurrency, $startDate, $endDate){

    $method = "GET";
    $url = self::EXCHANGE_URL . "/history";
    $data = array("base" => $baseCurrency, "start_at" => $startDate, "end_at" => $endDate);

    $result = $this->callAPI($method, $url, $data);
    if($result==null)
      return array(); // no results return empty array

    $resultObj = json_decode($result);
    $dates = array();
    foreach($resultObj->rates as $date => $rates){
      $currency_rates = array();
      foreach($rates as $currency => $rate){
        if($currency!=$baseCurrency)
          $currency_rates[$currency] = $rate;
      }
      ksort($currency_rates);
      $results[$date] = $currency_rates;
      krsort($results);
    }
    return $results;
  }

  public function getRatesByCode($baseCurrency, $startDate, $endDate){

    $method = "GET";
    $url = self::EXCHANGE_URL . "/history";
    $data = array("base" => $baseCurrency, "start_at" => $startDate, "end_at" => $endDate);

    $result = $this->callAPI($method, $url, $data);
    if($result==null)
      return array(); // no results return empty array

    $resultObj = json_decode($result);

    $codes = $this->getCurrencyCodes();
    $currency_codes = array();
    foreach($codes as $code){
      $currency_dates = array();
      foreach($resultObj->rates as $date => $rates){
        foreach($rates as $currency_code => $rate){
          if($code == $currency_code && $code!=$baseCurrency){
            $currency_dates[$date] = $rate;
          }
        }
      }
      krsort($currency_dates);
      $currency_codes[$code] = $currency_dates;
    }

    ksort($currency_codes);
    return $currency_codes;
  }

  // Method: POST, GET
  // URL : full url to API
  // Data: array("param" => "value") ==> index.php?param=value
  private function callAPI($method, $url, $data = false)
  {
      $curl = curl_init();
      switch ($method)
      {
          case "POST":
              curl_setopt($curl, CURLOPT_POST, 1);

              if ($data)
                  curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
              break;
          case "PUT":
              curl_setopt($curl, CURLOPT_PUT, 1);
              break;
          default:
              if ($data)
                  $url = sprintf("%s?%s", $url, http_build_query($data));
      }
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

      $result = curl_exec($curl);
      curl_close($curl);

      return $result;
  }
}

?>
