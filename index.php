<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
?>
<html>

<head>

  <style>
    table {
      border-collapse: collapse;
    }
    th, td {
      border: 1px solid black;
      padding: 8px;
      text-align: center;
    }
    
    .bad{
      color: red;
    }
    
    .good{
      color: green;
    }
    
    .current{
      background-color: #e6f2ff;
    }
  }
  </style>
</head>
<body>

<?php
$names = [
    "" => "",
    "5" => "Honza",
    "1" => "Jana",
    "2" => "Jitka",
    "11" => "Kuba",
    "8" => "Martin",
    "4" => "Martina",
    "6" => "Míra",
    "3" => "Pepík",
    "7" => "Tomáš",
];

$selectedPersonId = $_GET['personId'] ?? "";
$selectedDate = $_GET['date'] ?? "";
$selectedOrder = $_GET['order'] ?? "";
$selectedComplete = $_GET['complete'] ?? "";

?>

<form method="get" style="margin-bottom: 20px;">
    <label for="personId">Vyberte jméno pracovníka:</label>
    <select name="personId" id="personId">
        <?php foreach ($names as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>"
                <?= $value === $selectedPersonId ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">OK</button>
</form>


<?php

ini_set('display_errors', '1');


function compareDateTimes(DateTime $dateTime1, DateTime $dateTime2): int{
  $timestamp1 = $dateTime1->getTimeStamp();
  $timestamp2 = $dateTime2->getTimeStamp();
  
  if($timestamp1 == $timestamp2)
    return 0;
  
  return ($timestamp1 < $timestamp2) ? -1 : 1;  
}


function getDateString(DateTime $dateTime): string{
  return $dateTime->format("Y-m-d");
}


function getCzechDateWithWeekdayString(DateTime $dt): string{
  $weekdays = [
    1 => 'pondělí',
    2 => 'úterý',
    3 => 'středa',
    4 => 'čtvrtek',
    5 => 'pátek',
    6 => 'sobota',
    7 => 'neděle',
  ];

  $weekday = $weekdays[(int)$dt->format('N')];

  return sprintf(
    '%s %d.%d.%d',
    $weekday,
    (int)$dt->format('j'),
    (int)$dt->format('n'),
    (int)$dt->format('Y')
  );
}


function getTimeInMessage($time, $regularTime, $tillFrom){
  $comparisonResult = compareDateTimes($time, $regularTime);
  $lsResult = "";
  
  if($comparisonResult == -1){
    $lsResult = "<span class=\"" . (($tillFrom == "FROM") ? "bad\">již" : "good\">jen") . " ";
  }
  
  if($comparisonResult == 1){
    $lsResult = "<span class=\"" . (($tillFrom == "FROM") ? "good\"" : "bad\"") . ">až ";
  }
  
  $lsResult .= ($tillFrom == "FROM") ? "od " : "do ";
  
  $lsResult .= getTimeString($time);
  
  if($comparisonResult){
    $lsResult .= "</span>";
  }
  
  return $lsResult;
}


function getTimeString($dateTime){
  return $dateTime->format("G:i");
}


function str_getcsv_26($line){
  return str_getcsv($line, ",", '"', "\\");
}


class Shift{
  var $_date;
  var $order;
  
  
  public function __construct(DateTime $idDate, int $inOrder){
    $this->_date = $idDate;
    $this->order = $inOrder;
  }


  public function getCzechOrder(): string{
    switch($this->order){
      case 1: return "dlouhá";
      case 2: return "krátká";
      case 3: return "noční";
    }
  }
  
  
  public function getCzechDateWithWeekday(): string{
    return getCzechDateWithWeekdayString($this->_date);
  }
  
  
  public function getKey(){
    return getDateString($this->_date) . "\\" . $this->order;
  }
  
  
  public function getNeighbourhood($ibIncludeItself){
    $ret = Array();
        
    if($this->order == 1 || $this->order == 2){
      array_push($ret, new Shift((clone $this->_date)->modify('-1 day'), 3));
      
      if($ibIncludeItself)
        array_push($ret, new Shift((clone $this->_date), $this->order));
      
      array_push($ret, new Shift(clone $this->_date, ($this->order == 1) ? 2 : 1));
      array_push($ret, new Shift(clone $this->_date, 3));
    }
    else{
      array_push($ret, new Shift(clone $this->_date, 1));
      array_push($ret, new Shift(clone $this->_date, 2));
      
      if($ibIncludeItself)
        array_push($ret, new Shift((clone $this->_date), $this->order));
      
      array_push($ret, new Shift((clone $this->_date)->modify('+1 day'), 1));
      array_push($ret, new Shift((clone $this->_date)->modify('+1 day'), 2));
    }
    
    return $ret;
  }
  
  
  public function isCurrent(){
    return ($this->getKey() == ($GLOBALS["selectedDate"] . "\\" . $GLOBALS["selectedOrder"]));
  }
  

  public function getNeighbourhoodTD(){
    return "<td><a href=\"" . $this->getNeighbourhoodURL() . "\">detail střídání</td>"; 
  }
  
  
  public function getNeighbourhoodURL(){
    return "index.php?" . $this->getShiftURLParams();
  }
  
  
  public function getShiftURLParams(){
    return "date=" . getDateString($this->_date) . "&order=" . $this->order; 
  }
  
  
  public function isEqual(Shift $inShift){
    return ($this->getKey() == $inShift->getKey());
  }
}



class MonthShiftsListRecord{
  var $shift;
  var $_date;
  var $in;
  var $order;
  var $out;
  var $personId;
  var $personName;
  var $regularIn;
  var $regularOut;
  var $shiftMessage;
  
  
	public function __construct($input_array){
		if($input_array == null)
      return;
    
    $this->shift = new Shift(DateTime::createFromFormat('Y-m-d H:i:s', ($input_array[0] . " 00:00:00"), new DateTimeZone('UTC')), $input_array[1]);
    $this->personId = $input_array[4];
    $this->personName = $input_array[6];
    $this->in = new DateTime("1970-01-01 " . $input_array[9] . ":00", new DateTimeZone('UTC'));
    $this->out = new DateTime("1970-01-01 " . $input_array[10] . ":00", new DateTimeZone('UTC'));
    $this->regularIn = new DateTime("1970-01-01 " . $input_array[17] . ":00", new DateTimeZone('UTC'));
    $this->regularOut = new DateTime("1970-01-01 " . $input_array[18] . ":00", new DateTimeZone('UTC'));
    $this->shiftMessage = $this->getShiftMessage();
  }


  public function getKey(){
    return $this->shift->getKey();
  }
  
  
  public function getPersonNameTD(){
    return "<td><a href=\"" . $this->getPersonIdURL() . "\">" . $this->personName . "</td>"; 
  }
  
  
  public function getPersonIdURL(){
    return "index.php?personId=" . $this->personId . "&" . $this->shift->getShiftURLParams(); 
  }


  public function getTR4Neighbourhood(){
    return
      $this->getTRTag() .
        "<td>" . $this->shift->getCzechDateWithWeekday() .
        "</td><td>" . $this->shift->getCzechOrder() .
        "</td>" . $this->getPersonNameTD() .
        "<td>" .  $this->getShiftMessage() .
      "</td></tr>";
  }

  
  public function getTRTag(){
    return "<tr". (($this->shift->isCurrent()) ? " class=\"current\"" : "") . ">";
  }
  
  
  public function getTR4Person(){
    return $this->getTRTag() . "<td>" . $this->shift->getCzechDateWithWeekday() . "</td><td>" . $this->shift->getCzechOrder() . "</td><td>" . $this->getShiftMessage() . "</td>" . $this->shift->getNeighbourhoodTD() . "</tr>";
  }
  
  
  public function getShiftMessage(){
     return getTimeInMessage($this->in, $this->regularIn, "FROM") . " a " . getTimeInMessage($this->out, $this->regularOut, "TILL");
  }
    
  
  public function isInNeighbourhood($iaNeighbourhood){
    //print_r($iaNeighbourhood);
    foreach ($iaNeighbourhood as $shift){
      if($this->shift->isEqual($shift))
        return true;
    }
    
    return false;
  }
  
  
  public function isPerson($personId){
    return $this->personId == $personId;
  } 
 
}


class MonthShiftsList{
  var $records = array();
  var $personName = "";
  
  
  public function __construct($arrayMap, string $personId = "", $iaNeighbourhood = null){
    $this->initFromArrayMap($arrayMap, $personId, $iaNeighbourhood);
  }
  

  public function getTable4Neighbourhood(){
    $ret = "<table>";
    
    foreach($this->records as $record){
      $ret .= $record->getTR4Neighbourhood();
    }
    
    $ret .= "</table>";
    
    return $ret;
  }


  public function getTable4Person(){
    $ret = "<p>" . $this->personName . ":</p><table>";
    
    foreach($this->records as $record){
      $ret .= $record->getTR4Person();
    }
    
    $ret .= "</table>";
    
    return $ret;
  }
  
  
  public function init(){
    $this->records = array();
    $this->personName = "";
  }
  

  public function initFromArrayMap($arrayMap, string $personId = "", $iaNeighbourhood = null){
    $this->init();
    $this->personName = ($personId == "") ? "" : $GLOBALS["names"][$personId];
    
    $i = 0;
    
    foreach($arrayMap as $record){
      if($i){
        $monthShiftsListRecord = new MonthShiftsListRecord($record);
        if($iaNeighbourhood == null && ($personId == "" || $monthShiftsListRecord->isPerson($personId)) || ($iaNeighbourhood != null && $monthShiftsListRecord->isInNeighbourhood($iaNeighbourhood)))
          $this->records[$monthShiftsListRecord->getKey()] = $monthShiftsListRecord;
      }
      $i++;
    }
  }

  
  public function limitToPersonById($personId){
    foreach ($this->records as $record){
      if(!$record->isPerson($personId))
        unset($this->records[$record->getKey()]);
    }
  }
  
}

$monthShiftsListUrl = 'https://docs.google.com/spreadsheets/d/1ysbi-0T4SiMJxXUC3TZRgq263Q7QJO73RvLUdl3s1Lk/export?format=csv&gid=303224713';
$arrayMap = array_map('str_getcsv_26', file($monthShiftsListUrl));

if($selectedComplete == 1){
  $monthShiftsList = new MonthShiftsList($arrayMap, "", null);
  echo $monthShiftsList->getTable4Neighbourhood();
}
else{
  if($selectedPersonId !== "") {
    $monthShiftsList = new MonthShiftsList($arrayMap, $selectedPersonId, null);
    
    echo $monthShiftsList->getTable4Person();
  }
  else{
    if($selectedDate <> "" && $selectedOrder <> ""){
      $selectedShift = new Shift(DateTime::createFromFormat('Y-m-d H:i:s', ($selectedDate . " 00:00:00"), new DateTimeZone('UTC')), $selectedOrder);
      
      $neighbourhood = $selectedShift->getNeighbourhood(true);
      $monthShiftsList = new MonthShiftsList($arrayMap, "", $neighbourhood);  
      
      echo $monthShiftsList->getTable4Neighbourhood();
    }
  }
}
?>
<hr/>
<a href="/index.php" onclick="if (history.length > 1) history.back(); return false;">
    ← zpět
</a>
&nbsp;&nbsp;
<a href="index.php">Domů</a>
&nbsp;&nbsp;
<a href="index.php?complete=1">Kompletní směnář</a>
</body>
</html>