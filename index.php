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


function getCzechOrder(int $order): string{
  switch($order){
    case 1: return "dlouhá";
    case 2: return "krátká";
    case 3: return "noční";
  }
}



function getTimeInMessage(DateTime $time, DateTime $regularTime, string $tillFrom){
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


function getTimeString(DateTime $dateTime): string{
  return $dateTime->format("G:i");
}


function str_getcsv_26(string $line): array{
  return str_getcsv($line, ",", '"', "\\");
}


class Shift{
  public DateTime $_date;
  public int $order;
  
  
  public function __construct(DateTime $idDate, int $inOrder){
    $this->_date = $idDate;
    $this->order = $inOrder;
  }


  public function getCzechOrder(): string{
    return getCzechOrder($this->order);
  }
  
  
  public function getCzechDateWithWeekday(): string{
    return getCzechDateWithWeekdayString($this->_date);
  }
  
  
  public function getDateString(): string{
    return getDateString($this->_date);
  }
  
  
  public function getKey(): string{
    return $this->getDateString() . "\\" . $this->order;
  }
  
  
  public function getNeighbourhood($ibIncludeItself): Array{
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
  
  
  public function isCurrent(): bool{
    return ($this->getKey() == ($GLOBALS["selectedDate"] . "\\" . $GLOBALS["selectedOrder"]));
  }
  

  public function getNeighbourhoodTD(): string{
    return "<td><a href=\"" . $this->getNeighbourhoodURL() . "\">detail střídání</td>"; 
  }
  
  
  public function getNeighbourhoodURL(): string{
    return "index.php?" . $this->getShiftURLParams();
  }
  
  
  public function getShiftURLParams(): string{
    return "date=" . $this->getDateString() . "&order=" . $this->order; 
  }
  
  
  public function isEqual(Shift $inShift): bool{
    return ($this->getKey() == $inShift->getKey());
  }
}



class MonthShiftsListRecord{
  public Shift $shift;
  public DateTime $in;
  public DateTime $out;
  public int $personId;
  public string $personName;
  public DateTime $regularIn;
  public DateTime $regularOut;
  public string $shiftMessage;
  
  
	public function __construct(?array $input_array){
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


  public function getKey(): string{
    return $this->shift->getKey();
  }
  
  
  public function getPersonNameTD(): string{
    return "<td><a href=\"" . $this->getPersonIdURL() . "\">" . $this->personName . "</td>"; 
  }
  
  
  public function getPersonIdURL(): string{
    return "index.php?personId=" . $this->personId . "&" . $this->shift->getShiftURLParams(); 
  }


  public function getTR(): string{
    return
      $this->getTRTag() .
        "<td>" . $this->shift->getCzechDateWithWeekday() .
        "</td><td>" . $this->shift->getCzechOrder() .
        "</td>" . $this->getPersonNameTD() .
        "<td>" .  $this->getShiftMessage() .
      "</td></tr>";
  }

  
  public function getTRTag(): string{
    return "<tr". (($this->shift->isCurrent()) ? " class=\"current\"" : "") . ">";
  }
  
  
  public function getTR4Person(): string{
    return $this->getTRTag() . "<td>" . $this->shift->getCzechDateWithWeekday() . "</td><td>" . $this->shift->getCzechOrder() . "</td><td>" . $this->getShiftMessage() . "</td>" . $this->shift->getNeighbourhoodTD() . "</tr>";
  }
  
  
  public function getShiftMessage(): string{
     return getTimeInMessage($this->in, $this->regularIn, "FROM") . " a " . getTimeInMessage($this->out, $this->regularOut, "TILL");
  }
    
  
  public function isInNeighbourhood(array $iaNeighbourhood): bool{
    foreach ($iaNeighbourhood as $shift){
      if($this->shift->isEqual($shift))
        return true;
    }
    
    return false;
  }
  
  
  public function isPerson(int $personId): bool{
    return $this->personId == $personId;
  } 
 
}


class MonthShiftsList{
  public array $records = array(); //of MonthShiftsListRecord organized in an array with keys of string concatenation of date and order ("2026-01-01\\3"]
  public string $personName = "";
  public array $dates = array(); //of MonthShiftsListRecord organized in a 2-dimensional array with keys of date string "2026-01-01" and orders (1-3)
  
  
  public function __construct(array $arrayMap, ?int $personId = null, ?array $iaNeighbourhood = null){
    $this->initFromArrayMap($arrayMap, $personId, $iaNeighbourhood);
  }
  

  public function getTable(): string{
    $ret = "<table>";
    
    foreach($this->records as $record){
      $ret .= $record->getTR();
    }
    
    $ret .= "</table>";
    
    return $ret;
  }


  public function getTable4Person(): string{
    $ret = "<p>" . $this->personName . ":</p><table>";
    
    foreach($this->records as $record){
      $ret .= $record->getTR4Person();
    }
    
    $ret .= "</table>";
    
    return $ret;
  }
  
  
  public function getTableGroupedByDate(): string{
    $ret = "<table>";
    $ret .= "<tr><th>Date</th><th colspan=\"2\">" . getCzechOrder(1) . "</th><th colspan=\"2\">" . getCzechOrder(2) . "</th><th colspan=\"2\">" . getCzechOrder(3) . "</tr>";
    
    $ret .= "</table>";
    return $ret;
  }
  
  
  public function init(): void{
    $this->records = array();
    $this->personName = "";
    $this->dates = array();
  }
  
  
  public function initDates(): void{
    foreach($this->records as $record){
      $this->dates[$record->shift->getDateString()][$record->shift->order] = $record;
    }
    
    ksort($this->dates, SORT_STRING);
  }


  public function initFromArrayMap(array $arrayMap, ?int $personId = null, ?array $iaNeighbourhood = null): void{
    $this->init();
    $this->personName = ($personId == null) ? "" : $GLOBALS["names"][$personId];
    
    $i = 0;
    
    foreach($arrayMap as $record){
      if($i > 0){
        $monthShiftsListRecord = new MonthShiftsListRecord($record);
        if($iaNeighbourhood == null && ($personId == null || $monthShiftsListRecord->isPerson($personId)) || ($iaNeighbourhood != null && $monthShiftsListRecord->isInNeighbourhood($iaNeighbourhood)))
          $this->records[$monthShiftsListRecord->getKey()] = $monthShiftsListRecord;
      }
      $i++;
    }
    
    $this->initDates();
  }

  
  public function limitToPersonById(int $personId){
    foreach ($this->records as $record){
      if($record->isPerson($personId) == false)
        unset($this->records[$record->getKey()]);
    }
  }
}




$monthShiftsListUrl = 'https://docs.google.com/spreadsheets/d/1ysbi-0T4SiMJxXUC3TZRgq263Q7QJO73RvLUdl3s1Lk/export?format=csv&gid=303224713';
$arrayMap = array_map('str_getcsv_26', file($monthShiftsListUrl));

if($selectedComplete == 1){
  $monthShiftsList = new MonthShiftsList($arrayMap, null, null);

  //echo $monthShiftsList->getTableGroupedByDate();
  echo $monthShiftsList->getTable();
}
else{
  if($selectedPersonId !== "") {
    $monthShiftsList = new MonthShiftsList($arrayMap, ($selectedPersonId == "") ? null : (int)$selectedPersonId, null);
  
    echo $monthShiftsList->getTable4Person();
  }
  else{
    if($selectedDate <> "" && $selectedOrder <> ""){
      $selectedShift = new Shift(DateTime::createFromFormat('Y-m-d H:i:s', ($selectedDate . " 00:00:00"), new DateTimeZone('UTC')), $selectedOrder);
      
      $neighbourhood = $selectedShift->getNeighbourhood(true);
      $monthShiftsList = new MonthShiftsList($arrayMap, null, $neighbourhood);  
      
      echo $monthShiftsList->getTable();
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