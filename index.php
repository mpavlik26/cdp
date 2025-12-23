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
  }
  </style>
</head>

<?php
$names = [
    "" => "",
    "Honza" => "Honza",
    "Jana" => "Jana",
    "Jitka" => "Jitka",
    "Kuba" => "Kuba",
    "Martin" => "Martin",
    "Martina" => "Martina",
    "Míra" => "Míra",
    "Pepík" => "Pepík",
    "Tomáš" => "Tomáš",
];

$selectedName = $_GET['person'] ?? "";
?>

<form method="get" style="margin-bottom: 20px;">
    <label for="person">Vyberte jméno pracovníka:</label>
    <select name="person" id="person">
        <?php foreach ($names as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>"
                <?= $value === $selectedName ? 'selected' : '' ?>>
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


class MonthShiftsListRecord{
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
		$this->_date = DateTime::createFromFormat('Y-m-d H:i:s', ($input_array[0] . " 00:00:00"), new DateTimeZone('UTC'));
    $this->order = $input_array[1];
    $this->personId = $input_array[4];
    $this->personName = $input_array[6];
    $this->in = new DateTime("1970-01-01 " . $input_array[9] . ":00", new DateTimeZone('UTC'));
    $this->out = new DateTime("1970-01-01 " . $input_array[10] . ":00", new DateTimeZone('UTC'));
    $this->regularIn = new DateTime("1970-01-01 " . $input_array[17] . ":00", new DateTimeZone('UTC'));
    $this->regularOut = new DateTime("1970-01-01 " . $input_array[18] . ":00", new DateTimeZone('UTC'));
    $this->shiftMessage = $this->getShiftMessage();
  }
  
  
  public function getCzechOrder(): string{
    switch($this->order){
      case 1: return "dlouhá";
      case 2: return "krátká";
      case 3: return "noční";
    }
  }
  
  
  public function getTR(){
    return "<tr><td>" . getCzechDateWithWeekdayString($this->_date) . "</td><td>" . $this->getCzechOrder() . "</td><td>" . $this->getShiftMessage() . "</td></tr>";
  }
  
  
  public function getKey(){
    return getDateString($this->_date) . "\\" . $this->order;
  }
  
  
  public function getShiftMessage(){
     return getTimeInMessage($this->in, $this->regularIn, "FROM") . " a " . getTimeInMessage($this->out, $this->regularOut, "TILL");
  }
    
  
  public function isPerson($personName){
    return $this->personName == $personName;
  } 
 
}


class MonthShiftsList{
  var $records = array();
  
  
  public function __construct($arrayMap, string $personName = ""){
    $this->initFromArrayMap($arrayMap, $personName);
  }
  

  public function getTable(){
    $ret = "<table>";
    
    foreach($this->records as $record){
      $ret .= $record->getTR();
    }
    
    $ret .= "</table>";
    
    return $ret;
  }
  
  
  public function init(){
    $this->records = array();
  }
  

  public function initFromArrayMap($arrayMap, string $personName = ""){
    $this->init();
    $i = 0;
    
    foreach($arrayMap as $record){
      if($i){
        $monthShiftsListRecord = new MonthShiftsListRecord($record);
        if($personName == "" || $monthShiftsListRecord->personName == $personName)
          $this->records[$monthShiftsListRecord->getKey()] = $monthShiftsListRecord;
      }
      $i++;
    }
  }

  
  public function limitToPersonByName($personName){
    foreach ($this->records as $record){
      if(!$record->isPerson($personName))
        unset($this->records[$record->getKey()]);
    }
  }
  
}


$url = 'https://docs.google.com/spreadsheets/d/1ysbi-0T4SiMJxXUC3TZRgq263Q7QJO73RvLUdl3s1Lk/export?format=csv&gid=303224713';

if ($selectedName !== "") {
  $arrayMap = array_map('str_getcsv', file($url));
  $monthShiftsList = new MonthShiftsList($arrayMap, $selectedName);
  echo $monthShiftsList->getTable();
}

//print_r($monthShiftsList);



?>
</html>