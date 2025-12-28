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
    
    .issues{
      color: blue;
      font-style: italic;
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


enum ETableDisplay: int{
  case TR = 1;
  case DATE = 2;
  case ORDER = 4;
  case NAME = 8;
  case SHIFT_MESSAGE = 16;
  case NEIGHBOURHOOD = 32;
}


enum EIssueType: int{
  case WITHOUT_HANDOVER = 1;
  case WITHOUT_TAKEOVER = 2;
}


function compareDateTimes(DateTime $dateTime1, DateTime $dateTime2): int{
  $timestamp1 = $dateTime1->getTimeStamp();
  $timestamp2 = $dateTime2->getTimeStamp();
  
  if($timestamp1 == $timestamp2)
    return 0;
  
  return ($timestamp1 < $timestamp2) ? -1 : 1;  
}


function createDateTimeFromDateString(string $dateString): DateTime{
  return DateTime::createFromFormat('Y-m-d H:i:s', ($dateString . " 00:00:00"), new DateTimeZone('UTC'));
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
  
  
  public function createNeighbourhood($ibIncludeItself): Array{
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
  

  public function createNextShift(): ?Shift{
    switch($this->order){
      case 2:
        return new Shift((clone $this->_date), 3);
        break;
      case 3:
        return new Shift((clone $this->_date)->modify('+1 day'), 2);
        break;
      default:
        return null;
    }
  }

  
  public function createPreviousShift(): ?Shift{
    switch($this->order){
      case 2:
        return new Shift((clone $this->_date)->modify('-1 day'), 3);
        break;
      case 3:
        return new Shift((clone $this->_date), 2);
        break;
      default:
        return null;
    }
  }
  
  
  public function isCurrent(): bool{
    return ($this->getKey() == ($GLOBALS["selectedDate"] . "\\" . $GLOBALS["selectedOrder"]));
  }
  

  public function createNeighbourhoodTD(): string{
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


class Issue{
  public EIssueType $type;
  public string $message;

  
  public function __construct(EIssueType $type, ?string $message = ""){
    $this->type = $type;
    $this->message = $message;
  }
}


class Issues{
  public array $issues = array();


  public function __construct(){
    $this->issues = array();
  }
  
  
  public function add(Issue $issue){
    $this->issues[] = $issue;
  }
  
  
  public function count(): int{
    return count($this->issues);
  }
  
  
  public function getMessage(?string $separator = ", "){
    $ret = "";
    $i = 0;
    
    foreach($this->issues as $issue){
      $ret .= (($i > 0) ? $separator : "") . $issue->message;
      $i++;
    }
    
    return $ret;
  }
}



class MonthShiftsListRecord{
  public Issues $issues;
  public Shift $shift;
  public DateTime $in;
  public DateTime $out;
  public int $personId;
  public string $personName;
  public DateTime $regularIn;
  public DateTime $regularOut;
  
  
	public function __construct(?array $input_array){
		if($input_array == null)
      return;

    $this->issues = new Issues();
    
    $this->shift = new Shift(createDateTimeFromDateString($input_array[0]), $input_array[1]);
    $this->personId = $input_array[4];
    $this->personName = $input_array[6];
    $this->in = new DateTime("1970-01-01 " . $input_array[9] . ":00", new DateTimeZone('UTC'));
    $this->out = new DateTime("1970-01-01 " . $input_array[10] . ":00", new DateTimeZone('UTC'));
    $this->regularIn = new DateTime("1970-01-01 " . $input_array[17] . ":00", new DateTimeZone('UTC'));
    $this->regularOut = new DateTime("1970-01-01 " . $input_array[18] . ":00", new DateTimeZone('UTC'));
  }


  public function findIssues(MonthShiftsList $monthShiftsList): void{
    if($this->shift->order == 2 || $this->shift->order == 3){
      if(($previousShift = $this->shift->createPreviousShift()) != null){  
        if(($previousRecord = $monthShiftsList->records[$previousShift->getKey()] ?? null) != null){
          if(compareDateTimes($previousRecord->out, $this->in) == -1){
            $this->issues->add(new Issue(EIssueType::WITHOUT_TAKEOVER, "v " . getTimeString($this->in) . " bez převzetí"));
          }
        }
      }
      if(($nextShift = $this->shift->createNextShift()) != null){  
        if(($nextRecord = $monthShiftsList->records[$nextShift->getKey()] ?? null) != null){
          if(compareDateTimes($this->out, $nextRecord->in) == -1){
            $this->issues->add(new Issue(EIssueType::WITHOUT_HANDOVER, "v " . getTimeString($this->out) . " bez předání"));
          }
        }
      }
    }
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


  public function getTR(int $whatToDisplay): string{
    return
      (($whatToDisplay & ETableDisplay::TR->value) ? $this->getTRTag() : "") .
        (($whatToDisplay & ETableDisplay::DATE->value) ? "<td>" . $this->shift->getCzechDateWithWeekday() . "</td>" : "") .
        (($whatToDisplay & ETableDisplay::ORDER->value) ? "<td>" . $this->shift->getCzechOrder() . "</td>" : "") .
        (($whatToDisplay & ETableDisplay::NAME->value) ? $this->getPersonNameTD() : "") .
        (($whatToDisplay & ETableDisplay::SHIFT_MESSAGE->value) ? "<td>" .  $this->getShiftMessage() . "</td>" : "") .
        (($whatToDisplay & ETableDisplay::NEIGHBOURHOOD->value) ? $this->shift->createNeighbourhoodTD() : "") .
      (($whatToDisplay & ETableDisplay::TR->value) ? "</tr>" : "" );
  }

  
  public function getTRTag(): string{
    return "<tr". (($this->shift->isCurrent()) ? " class=\"current\"" : "") . ">";
  }
  
  
  public function getShiftMessage(): string{
    $ret =
      getTimeInMessage($this->in, $this->regularIn, "FROM") .
      " a " .
      getTimeInMessage($this->out, $this->regularOut, "TILL");

    if($this->issues->count() > 0){
      $ret .= ";<br><span class=\"issues\">";
      $ret .= "(" . $this->issues->getMessage() . ")";
      $ret .= "</span>";
    }
       
    return $ret;  
       
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
  
  
  public function findIssues(){
     foreach($this->records as $record){
       $record->findIssues($this);
     }
  }
  

  public function getTable(): string{
    $ret = "<table>";
    
    foreach($this->records as $record){
      $ret .= $record->getTR(ETableDisplay::TR->value | ETableDisplay::DATE->value | ETableDisplay::ORDER->value | ETableDisplay::NAME->value | ETableDisplay::SHIFT_MESSAGE->value);
    }
    
    $ret .= "</table>";
    
    return $ret;
  }


  public function getTable4Person(): string{
    $ret = "<p>" . $this->personName . ":</p><table>";
    
    foreach($this->records as $record){
      $ret .= $record->getTR(ETableDisplay::TR->value| ETableDisplay::DATE->value | ETableDisplay::ORDER->value | ETableDisplay::SHIFT_MESSAGE->value | ETableDisplay::NEIGHBOURHOOD->value);
    }
    
    $ret .= "</table>";
    
    return $ret;
  }
  
  
  public function getTableGroupedByDate(): string{
    $ret = "<table>";
    $ret .= "<tr><th>Datum</th><th colspan=\"2\">" . getCzechOrder(1) . "</th><th colspan=\"2\">" . getCzechOrder(2) . "</th><th colspan=\"2\">" . getCzechOrder(3) . "</tr>";
    
    foreach($this->dates as $date => $records){
      $ret .= "<tr>";
      
      $ret .= "<td><b>" . getCzechDateWithWeekdayString(createDateTimeFromDateString($date)) . "</b></td>";
      
      foreach($records as $record){
        $ret .= $record->getTR(ETableDisplay::NAME->value | ETableDisplay::SHIFT_MESSAGE->value);
      }
      
      $ret .= "</tr>";
    }
    
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
    
    ksort($this->dates, SORT_STRING); //sort according to date
    
    foreach($this->dates as $records){
      ksort($records, SORT_NUMERIC); //sort according to order
    }
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
    $this->findIssues();
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

  echo $monthShiftsList->getTableGroupedByDate();
  //echo $monthShiftsList->getTable();
}
else{
  if($selectedPersonId !== "") {
    $monthShiftsList = new MonthShiftsList($arrayMap, ($selectedPersonId == "") ? null : (int)$selectedPersonId, null);
  
    echo $monthShiftsList->getTable4Person();
  }
  else{
    if($selectedDate <> "" && $selectedOrder <> ""){
      $selectedShift = new Shift(createDateTimeFromDateString($selectedDate), $selectedOrder);
      
      $neighbourhood = $selectedShift->createNeighbourhood(true);
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