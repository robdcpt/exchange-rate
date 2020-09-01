<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="stylesheet" type="text/css" href="style.css">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
  <title>Exchange Rates</title>
</head>
<body>

<?php include 'ExchangeApi.php';?>
<?php include "DatabaseApi.php";?>

<?php
// initialize variables
$dbApi = new DatabaseApi();
$exApi = new ExchangeApi();
$searches = $dbApi->getSearches(20);
$codes = null;
$selected_code = null;
$code_rates = null;
$submit = false;
$startdate = $enddate = "";
$code = "Choose ...";
$valid_startdate = true;
$valid_enddate = true;
$valid_code = true;

// handle various types of form posts
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $submit = true;
  $action = scrub_data($_POST["action"]);
  if($action=="savesearch"){
    $startdate = scrub_data($_POST["startdate"]);
    $enddate = scrub_data($_POST["enddate"]);
    $code = scrub_data($_POST["currencycode"]);
    $value = ($_POST["savesearch"]);
    if($value=="save"){
      if($dbApi->insertSearch($code, $startdate, $enddate)){
        // save rate results - note these always come from API intentionally
        $code_rates = $exApi->getRatesByCode($code, $startdate, $enddate);
        foreach($code_rates as $curr_code => $date_rate){
          if($code!= $curr_code){
            foreach($date_rate as $date => $rate){
              $dbApi->insertRate($code, $curr_code, $rate, $date);
            }
          }
        }
      }
      $searches = $dbApi->getSearches(20); // limit this to last 20
    }
    $submit = false;

  }else if($action=="deletesearch"){

    $created = ($_POST["searchvalue"]);
    $dbApi->deleteSearch($created);
    $searches = $dbApi->getSearches(20);
    $submit = false;

  }else if($action=="showsearch"){

    $created = ($_POST["searchvalue"]);
    if($searches!=null && !empty($searches)){
      foreach($searches as $search){

          if($search[3]==$created){
            $code = $search[0];
            $startdate = $search[1];
            $enddate = $search[2];
            $valid_startdate =  $valid_enddate =  $valid_code = true;
            $validate_post = false;
            $submit = false;
          }
      }
    }
  }else if($action=="search"){

    $startdate = scrub_data($_POST["startdate"]);
    $enddate = scrub_data($_POST["enddate"]);
    $code = scrub_data($_POST["currencycode"]);
    $valid_startdate = validate_startdate($startdate);
    $valid_enddate = validate_enddate($startdate, $enddate);
    $valid_code = validate_code($code);
    if($valid_startdate && $valid_enddate && $valid_code){
      $validate_post = true;
    }else{
      $validate_post = false;
      $submit=false;
    }
  }
}

function scrub_data($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

function validate_startdate($startdate) {
  if($startdate > date("Y-m-d")) // check not in future
    return false;
  return validateDate($startdate);
}

function validate_enddate($startdate, $enddate) {
  if($startdate > $enddate) // check not before start date
    return false;
  return validateDate($enddate);
}

function validate_code($code) {
  if(strlen($code)!=3) // sime length check
    return false;
  return true;
}

function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

?>
  <div class="container">
    <!-- search form start -->
    <h1>Exchange Rates</h1>
    <br>
    <div class="row">
    <div class="col-sm">
      <form class="form needs-validation" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post">
        <input type="hidden" name="action" value="search">
        <div class="form-group row">
          <label class="col-sm-3 col-form-label" for="startdate">Start Date</label>
          <div class="col-sm">
            <input class="form-control" type="date" id="startdate" name="startdate" value="<?=$startdate?>">
<?php        if($valid_startdate==false){ ?>
                <div class="alert alert-danger alert-dismissible fade show">
                choose a valid start date.
                </div>
<?php        } ?>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-sm-3 col-form-label" for="enddate">End Date</label>
          <div class="col-sm">
            <input class="form-control" type="date" id="enddate" name="enddate" value="<?=$enddate?>">
<?php       if($valid_enddate==false){ ?>
              <div class="alert alert-danger alert-dismissible fade show">
              choose a valid end date.
            </div>
<?php       } ?>
          </div>
        </div>

        <div class="form-group row">
          <label class="col-sm-3 col-form-label" for="currencycode">Base Currency</label>
          <div class="col-sm">
            <select class="custom-select my-1 mr-sm-2" id="currencycode" name="currencycode"  value="<?=$code?>">
              <option selected><?=$code?></option>
<?php
              $codes = $exApi->getCurrencyCodes();
              if($selected_code==null)
                $selected_code  = $codes[0];
              foreach($codes as $currency_code){
                echo "<option value=\"" . $currency_code . "\">" . $currency_code . "</option>";
              }
?>
            </select>
<?php        if($valid_code==false){  ?>
                <div class="alert alert-danger alert-dismissible fade show">
                choose a currency code.
                </div>
<?php       } ?>
          </div>
        </div>
        <button type="submit" class="btn btn-primary my-1">Submit</button>
      </form>
      <br>
      <!-- search form end -->

      <!-- rates history start -->
<?php
    if($submit==true){
      if($validate_post==true){
        echo "<h5>Exchange Rate History for " . $code . "</h5>";
        echo "<div class=\"col-sm\">";
        echo "<div id=\"rates-box\">";
        $code_rates = null;
        if($dbApi->checkSearchRange($code, $startdate, $enddate)>0){
           // use database to retrieve results
          $code_rates = $dbApi->getRatesByCode($code, $startdate, $enddate);
        }else{
          // use API to retrieve results
          $code_rates = $exApi->getRatesByCode($code, $startdate, $enddate);
        }
        if($code_rates!=null){
          foreach($code_rates as $curr_code => $date_rate){
            if($code!= $curr_code){

              echo "<div class=\"row\">";
              echo "<div class=\"col\">";
              echo "<b>Currency: " . $curr_code . "</b>";
              echo "</div>";
              echo "<div class=\"col\">";
              echo "<b>Date</b>";
              echo "</div>";
              echo "<div class=\"col\">";
              echo "<b>Rate</b>";
              echo "</div>";
              echo "</div>";

              $min = 100000; $max = 0; $avg = 0; $total=0; $rate_count=0;
              foreach($date_rate as $date => $rate){
                $total += $rate;
                $rate_count +=1;
                if($rate<$min)
                  $min = $rate;
                if($rate>$max)
                  $max = $rate;

                echo "<div class=\"row\">";
                    echo "<div class=\"col\">";
                    echo "</div>";
                echo "<div class=\"col\">";
                echo $date;
                echo "</div>;";
                echo "<div class=\"col\">";
                echo $rate;
                echo "</div>";
                echo "</div>";
              }
              $avg = round($total / $rate_count,10);

            echo "<div class=\"row\">";
              echo "<div class=\"col\">";
                echo "Minimum";
              echo "</div>";
              echo "<div class=\"col\">";
                echo "Maximum";
              echo "</div>";
              echo "<div class=\"col\">";
                echo "Average";
              echo "</div>";
            echo "</div>";
            echo "<div class=\"row\">";
              echo "<div class=\"col\">";
                echo $min;
              echo "</div>";
              echo "<div class=\"col\">";
                echo $max;
              echo "</div>";
              echo "<div class=\"col\">";
                echo $avg;
              echo "</div>";
            echo "</div>";
            echo "<br>";
            }
          }
        }
        echo "</div>";
        echo "</div>";
      }
    }
?>
    </div>
    <div class="col-sm">
      <div class="row">
        <div class="col-sm-2">
<?php
    if($submit==true){
      if($validate_post==true){
        echo "<form action=" . htmlspecialchars($_SERVER["PHP_SELF"]) . " method=\"post\">";
          echo "<input type=\"hidden\" id=\"startdate\" name=\"startdate\" value=\"" . $startdate . "\">";
          echo "<input type=\"hidden\" id=\"enddate\" name=\"enddate\" value=\"" . $enddate . "\">";
          echo "<input type=\"hidden\" id=\"currencycode\" name=\"currencycode\" value=\"" . $code . "\">";
          echo "<input type=\"hidden\" name=\"action\" value=\"savesearch\">";
          echo "<button type=\"submit\" name=\"savesearch\" value=\"save\" class=\"btn btn-light btn-sm\">Save Search</button>";
          echo "<br><br>";
          echo "<button type=\"submit\" name=\"savesearch\" value=\"cancel\" class=\"btn btn-light btn-sm\">Back</button>";
        echo "</form>";
      }
    }
?>
        </div>
        <div class="col-sm">
<?php
          if($submit==false){
            if($searches!==null && !empty($searches)){ // loaded above

                echo "<h5>Recent Searches</h5>";
                echo "<div class=\"row\">";
                  echo "<div class=\"col-sm-2\">";
                  echo "Base";
                  echo "</div>";
                  echo "<div class=\"col-sm-3\">";
                  echo "Start Date";
                  echo "</div>";
                  echo "<div class=\"col-sm-3\">";
                  echo "End Date";
                  echo "</div>";
                echo "</div> ";
                foreach($searches as $search){
                     echo "<div class=\"row\">";
                       echo "<div class=\"col-sm-2\">";
                        echo "<div id=\"search-text\">";
                          echo $search[0];
                        echo "</div>";
                       echo "</div>";
                       echo "<div class=\"col-sm-3\">";
                        echo "<div id=\"search-text\">";
                          echo $search[1];
                        echo "</div>";
                       echo "</div>";
                       echo "<div class=\"col-sm-3\">";
                        echo "<div id=\"search-text\">";
                          echo $search[2];
                        echo "</div>";
                       echo "</div>";
                       echo "<div class=\"col-sm-2\">";

                       echo "<form action=" . htmlspecialchars($_SERVER["PHP_SELF"]) . " method=\"post\">";
                         echo "<input type=\"hidden\" id=\"startdate\" name=\"startdate\" value=\"" . $startdate . "\">";
                         echo "<input type=\"hidden\" id=\"enddate\" name=\"enddate\" value=\"" . $enddate . "\">";
                         echo "<input type=\"hidden\" id=\"currencycode\" name=\"currencycode\" value=\"" . $code . "\">";
                         echo "<input type=\"hidden\" name=\"action\" value=\"showsearch\">";
                         echo "<button type=\"submit\" name=\"searchvalue\" value=\"" . $search[3] . "\" class=\"btn btn btn-light btn-sm\">copy</button>";
                       echo "</form>";

                       echo "</div>";
                       echo "<div class=\"col-sm-2\">";

                       echo "<form action=" . htmlspecialchars($_SERVER["PHP_SELF"]) . " method=\"post\">";
                         echo "<input type=\"hidden\" id=\"startdate\" name=\"startdate\" value=\"" . $startdate . "\">";
                         echo "<input type=\"hidden\" id=\"enddate\" name=\"enddate\" value=\"" . $enddate . "\">";
                         echo "<input type=\"hidden\" id=\"currencycode\" name=\"currencycode\" value=\"" . $code . "\">";
                         echo "<input type=\"hidden\" name=\"action\" value=\"deletesearch\">";
                         echo "<button type=\"submit\" name=\"searchvalue\" value=\"" . $search[3] . "\" class=\"btn btn btn-light btn-sm\">delete</button>";
                       echo "</form>";

                       echo "</div>";
                     echo "</div>";
              }
            }
          }
?>
        </div>
      </div>
    </div>
    </div>
  </div>

</div><!--row><!-->
</div> <!--container><!-->

    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
  </body>
</html>
