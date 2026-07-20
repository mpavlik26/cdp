<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=utf-8');

$names = [
    "" => "",
    "5" => "Honza",
    "15" => "Ivona",
    "1" => "Jana",
    "2" => "Jitka",
    "14" => "Káťa",
    "11" => "Kuba",
    "18" => "Lucka",
    "13" => "Martin J.",
    "8" => "Martin P.",
    "4" => "Martina",
    "6" => "Míra",
    "16" => "Pavel B.",
    "17" => "Pavol F.",
    "3" => "Pepík",
    "7" => "Tomáš",
    "12" => "Veronika"
];

const HANDOVER_BUFFER_MINUTES = 15; //normal handover overlap between 524 and night shifts; only a bigger gap counts as covering 514

const SHIFT_GOOD = '#1B7F3B';
const SHIFT_BAD = '#C43331';
const SHIFT_INK = '#1B2530';

const ROLE_META = [
  'd' => ['label' => 'Dlouhá 514', 'code' => '514', 'color' => '#C0223F', 'bg' => '#FBE1E5'],
  'k' => ['label' => 'Krátká 524', 'code' => '524', 'color' => '#4B5563', 'bg' => '#ECEFF2'],
  'n' => ['label' => 'Noční', 'code' => '', 'color' => '#4B5563', 'bg' => '#ECEFF2'],
];


function compareDateTimes(DateTime $dateTime1, DateTime $dateTime2): int{
  $timestamp1 = $dateTime1->getTimeStamp();
  $timestamp2 = $dateTime2->getTimeStamp();

  return $timestamp1 - $timestamp2;
}


function createDateTimeFromDateString(string $dateString): DateTime{
  return DateTime::createFromFormat('Y-m-d H:i:s', ($dateString . " 00:00:00"), new DateTimeZone('UTC'));
}


function getCurrentDate(){
  return createDateTimeFromDateString(getDateString(new DateTime()));
}


function getDateString(DateTime $dateTime): string{
  return $dateTime->format("Y-m-d");
}


function getTimeString(DateTime $dateTime): string{
  return $dateTime->format("G:i");
}


function getCzechWeekdayName(DateTime $dt): string{
  $weekdays = [
    1 => 'pondělí',
    2 => 'úterý',
    3 => 'středa',
    4 => 'čtvrtek',
    5 => 'pátek',
    6 => 'sobota',
    7 => 'neděle',
  ];

  return $weekdays[(int)$dt->format('N')];
}


function getCzechMonthAbbrev(int $month): string{
  $months = [
    1 => 'LED', 2 => 'ÚNO', 3 => 'BŘE', 4 => 'DUB', 5 => 'KVĚ', 6 => 'ČVN',
    7 => 'ČVC', 8 => 'SRP', 9 => 'ZÁŘ', 10 => 'ŘÍJ', 11 => 'LIS', 12 => 'PRO',
  ];

  return $months[$month];
}


function getCzechMonthGenitive(int $month): string{
  $months = [
    1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben', 5 => 'květen', 6 => 'červen',
    7 => 'červenec', 8 => 'srpen', 9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec',
  ];

  return $months[$month];
}


function formatMonthRangeLabel(DateTime $from, DateTime $to): string{
  $fromMonth = (int)$from->format('n');
  $toMonth = (int)$to->format('n');
  $fromYear = $from->format('Y');
  $toYear = $to->format('Y');

  if ($fromYear === $toYear && $fromMonth === $toMonth){
    return getCzechMonthGenitive($fromMonth) . ' ' . $fromYear;
  }

  if ($fromYear === $toYear){
    return getCzechMonthGenitive($fromMonth) . ' – ' . getCzechMonthGenitive($toMonth) . ' ' . $fromYear;
  }

  return getCzechMonthGenitive($fromMonth) . ' ' . $fromYear . ' – ' . getCzechMonthGenitive($toMonth) . ' ' . $toYear;
}


function classifyTime(DateTime $actual, DateTime $regular, string $edge): string{
  $cmp = compareDateTimes($actual, $regular);

  if ($cmp === 0) return 'ink';

  if ($edge === 'start') return $cmp < 0 ? 'bad' : 'good';

  return $cmp > 0 ? 'bad' : 'good';
}


function str_getconfig_line(string $line){
  return explode("=", $line);
}


function isCzechHoliday(DateTime $dateTime): bool{
  $holidays2 = ["01-01", "05-01", "05-08", "07-05", "07-06", "09-28", "10-28", "11-17", "12-24", "12-25", "12-26"];
  $holidays3 = ["2026-04-03", "2026-04-06", "2027-03-26", "2027-03-29", "2028-04-14", "2028-04-17", "2029-03-30", "2029-04-02", "2030-04-19", "2030-04-22"];

  return in_array($dateTime->format("Y-m-d"), $holidays3) || in_array($dateTime->format("m-d"), $holidays2);
}


function isInWeekend(DateTime $dateTime): bool{
  $weekday = (int)$dateTime->format('N');
  return ($weekday == 6 || $weekday == 7 || isCzechHoliday($dateTime));
}


function str_getcsv_26(string $line): array{
  return str_getcsv($line, ",", '"', "\\");
}


class Config{
  public array $items = array();


  public function __construct(string $fileName){
    $arrayMap = array_map('str_getconfig_line', file($fileName));

    foreach ($arrayMap as $configLine){
      $this->items[$configLine[0]] = trim($configLine[1]);
    }
  }


  public function getValue(string $isKey): ?string{
    return array_key_exists($isKey, $this->items) ? $this->items[$isKey] : null;
  }
}


class DatesBetween{
  public ?DateTime $from = null;
  public ?DateTime $to = null;


  public function __construct(Config $config){
    if($configTo = $config->getValue("datesBetween_to")){
      $this->to = createDateTimeFromDateString($configTo);
    }

    if($configFromAutoDaysBeforeToday = $config->getValue("datesBetween_from_auto_days_before_today")){
      $this->from = getCurrentDate()->modify("-" . $configFromAutoDaysBeforeToday . " day");
    }
    else{
      if($configFrom = $config->getValue("datesBetween_from")){
        $this->from = createDateTimeFromDateString($configFrom);
      }
    }
  }


  public function isIn(DateTime $_date){
    if($this->from && $this->to){
      $fromTs = $this->from->getTimeStamp();
      $toTs = (((clone ($this->to))->modify('+1 day'))->modify('-1 second'))->getTimeStamp();
      $dateTs = $_date->getTimeStamp();
      return ($dateTs >= $fromTs && $dateTs <= $toTs);
    }
    else
      return true;//if there's nothing set, we will not limit the result set displayed
  }
}


class Shift{
  public DateTime $_date;
  public int $order;


  public function __construct(DateTime $idDate, int $inOrder){
    $this->_date = $idDate;
    $this->order = $inOrder;
  }


  public function getDateString(): string{
    return getDateString($this->_date);
  }


  public function getKey(): string{
    return $this->getDateString() . "\\" . $this->order;
  }


  public function isBetween(DatesBetween $datesBetween): bool{
    return $datesBetween->isIn($this->_date);
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


  public function __construct(?array $input_array){
    if($input_array == null)
      return;

    $this->shift = new Shift(createDateTimeFromDateString($input_array[0]), (int)$input_array[1]);
    $this->personId = (int)$input_array[4];
    $this->personName = $input_array[6];
    $this->in = new DateTime("1970-01-01 " . $input_array[9] . ":00", new DateTimeZone('UTC'));
    $this->out = new DateTime("1970-01-01 " . $input_array[10] . ":00", new DateTimeZone('UTC'));
    $this->regularIn = new DateTime("1970-01-01 " . $input_array[17] . ":00", new DateTimeZone('UTC'));
    $this->regularOut = new DateTime("1970-01-01 " . $input_array[18] . ":00", new DateTimeZone('UTC'));
  }


  public function isBetween(DatesBetween $datesBetween): bool{
    return $this->shift->isBetween($datesBetween);
  }
}


function buildShiftEntry(MonthShiftsListRecord $record): array{
  $startFlag = classifyTime($record->in, $record->regularIn, 'start');
  $endFlag = classifyTime($record->out, $record->regularOut, 'end');
  $hasBad = $startFlag === 'bad' || $endFlag === 'bad';
  $hasGood = !$hasBad && ($startFlag === 'good' || $endFlag === 'good');

  return [
    'person' => $record->personName,
    's' => ((int)$record->in->format('H')) * 60 + (int)$record->in->format('i'),
    'e' => ((int)$record->out->format('H')) * 60 + (int)$record->out->format('i'),
    'preKind' => '',
    'postKind' => '',
    'startStr' => getTimeString($record->in),
    'endStr' => getTimeString($record->out),
    'startColor' => $startFlag === 'bad' ? SHIFT_BAD : ($startFlag === 'good' ? SHIFT_GOOD : SHIFT_INK),
    'endColor' => $endFlag === 'bad' ? SHIFT_BAD : ($endFlag === 'good' ? SHIFT_GOOD : SHIFT_INK),
    'badgeShow' => $hasBad || $hasGood,
    'badgeText' => $hasBad ? 'DELŠÍ SMĚNA' : ($hasGood ? 'KRATŠÍ SMĚNA' : ''),
    'badgeBg' => $hasBad ? '#FBE9E8' : '#E6F4EA',
    'badgeFg' => $hasBad ? SHIFT_BAD : SHIFT_GOOD,
  ];
}


function groupDaysByMonth(array $days): array{
  $groups = [];

  foreach ($days as $day){
    $key = $day['year'] . '-' . $day['month'];

    if (!isset($groups[$key])){
      $groups[$key] = [
        'label' => ucfirst(getCzechMonthGenitive($day['month'])) . ' ' . $day['year'],
        'days' => [],
      ];
    }

    $groups[$key]['days'][] = $day;
  }

  return array_values($groups);
}


class MonthShiftsList{
  public array $records = array(); //of MonthShiftsListRecord organized in an array with keys of string concatenation of date and order ("2026-01-01\\3"]
  public array $dates = array(); //of MonthShiftsListRecord organized in a 2-dimensional array with keys of date string "2026-01-01" and orders (1-3)

  public DatesBetween $datesBetween;


  public function __construct(array $arrayMap){
    $this->init();
    $this->initFromArrayMap($arrayMap);
  }


  public function init(): void{
    $this->records = array();
    $this->dates = array();
    $this->datesBetween = new DatesBetween($GLOBALS["config"]);
  }


  public function initDates(): void{
    foreach($this->records as $record){
      $this->dates[$record->shift->getDateString()][$record->shift->order] = $record;
    }

    ksort($this->dates, SORT_STRING); //sort according to date

    foreach($this->dates as $dateKey => $records){
      ksort($this->dates[$dateKey], SORT_NUMERIC); //sort according to order
    }
  }


  public function initFromArrayMap(array $arrayMap): void{
    $i = 0;

    foreach($arrayMap as $record){
      if($i > 0){
        $monthShiftsListRecord = new MonthShiftsListRecord($record);

        if($monthShiftsListRecord->isBetween($this->datesBetween))
          $this->records[$monthShiftsListRecord->shift->getKey()] = $monthShiftsListRecord;
      }
      $i++;
    }

    $this->initDates();
  }


  public function buildDesignModel(): array{
    $from = $this->datesBetween->from;
    $to = $this->datesBetween->to;

    if ($from === null || $to === null){
      $dateKeys = array_keys($this->dates);
      sort($dateKeys, SORT_STRING);
      $from = $from ?? ($dateKeys ? createDateTimeFromDateString(reset($dateKeys)) : getCurrentDate());
      $to = $to ?? ($dateKeys ? createDateTimeFromDateString(end($dateKeys)) : getCurrentDate());
    }

    $todayStr = getDateString(getCurrentDate());

    $days = [];
    $cursor = clone $from;

    while (compareDateTimes($cursor, $to) <= 0){
      $dateStr = getDateString($cursor);
      $recordsForDate = $this->dates[$dateStr] ?? [];

      $days[] = [
        'date' => (int)$cursor->format('j') . '.' . (int)$cursor->format('n') . '.' . $cursor->format('Y'),
        'weekday' => getCzechWeekdayName($cursor),
        'dow' => (int)$cursor->format('w'),
        'weekend' => isInWeekend($cursor),
        'today' => $dateStr === $todayStr,
        'firstOfMonth' => (int)$cursor->format('j') === 1,
        'num' => (int)$cursor->format('j'),
        'month' => (int)$cursor->format('n'),
        'year' => (int)$cursor->format('Y'),
        'monthShort' => getCzechMonthAbbrev((int)$cursor->format('n')),
        'd' => isset($recordsForDate[1]) ? buildShiftEntry($recordsForDate[1]) : null,
        'k' => isset($recordsForDate[2]) ? buildShiftEntry($recordsForDate[2]) : null,
        'n' => isset($recordsForDate[3]) ? buildShiftEntry($recordsForDate[3]) : null,
      ];

      $cursor->modify('+1 day');
    }

    $count = count($days);

    for ($i = 0; $i < $count; $i++){
      $d = $days[$i]['d'];
      $k = $days[$i]['k'];
      $n = $days[$i]['n'];
      $prev = $i > 0 ? $days[$i - 1] : null;
      $next = $i < $count - 1 ? $days[$i + 1] : null;
      $prevNightEnd = ($prev !== null && $prev['n'] !== null) ? $prev['n']['e'] : null;

      if ($k !== null){
        if ($prevNightEnd !== null && $prevNightEnd - $k['s'] > HANDOVER_BUFFER_MINUTES){
          $k['preKind'] = '514';
        } elseif ($prevNightEnd !== null && $k['s'] > $prevNightEnd){
          $k['preKind'] = 'no';
        } else {
          $k['preKind'] = '';
        }

        if ($n !== null && $k['e'] - $n['s'] > HANDOVER_BUFFER_MINUTES){
          $k['postKind'] = '514';
        } elseif ($n !== null && $k['e'] < $n['s']){
          $k['postKind'] = 'no';
        } else {
          $k['postKind'] = '';
        }
      }

      if ($n !== null){
        $n['preKind'] = ($k !== null && $n['s'] > $k['e']) ? 'no' : '';
        $nextK = ($next !== null) ? $next['k'] : null;
        $n['postKind'] = ($nextK !== null && $n['e'] < $nextK['s']) ? 'no' : '';
      }

      $days[$i]['k'] = $k;
      $days[$i]['n'] = $n;
    }

    $peopleSet = [];
    foreach ($this->records as $record){
      $peopleSet[$record->personName] = true;
    }
    $people = array_keys($peopleSet);
    sort($people, SORT_STRING);

    $months = array_map(
      fn($group) => ['label' => $group['label'], 'span' => count($group['days'])],
      groupDaysByMonth($days)
    );

    return [
      'dates' => $days,
      'people' => $people,
      'roleMeta' => ROLE_META,
      'months' => $months,
    ];
  }
}


function buildDetailModel(array $model, int $gi, string $key): array{
  $days = $model['dates'];
  $d = $days[$gi];
  $roleMeta = $model['roleMeta'];

  $P = function(array $a, string $roleKey) use ($roleMeta): array{
    return [
      'name' => $a['person'], 'role' => $roleMeta[$roleKey]['label'], 'color' => $roleMeta[$roleKey]['color'], 'bg' => $roleMeta[$roleKey]['bg'],
      'startStr' => $a['startStr'], 'endStr' => $a['endStr'], 'startColor' => $a['startColor'], 'endColor' => $a['endColor'],
      'sep' => $roleKey === 'n' ? '→' : '–',
    ];
  };

  $noNote = ['hasNote' => false, 'note' => '', 'noteBg' => '', 'noteFg' => '', 'noteBorder' => '', 'noteIcon' => '', 'noteWarn' => false, 'noteMove' => false];
  $WARN = ['noteBg' => '#FDF0D5', 'noteFg' => '#8A5A00', 'noteBorder' => '#E7B85C', 'noteIcon' => '', 'noteWarn' => true, 'noteMove' => false];
  $MOVE = ['noteBg' => '#FBE1E5', 'noteFg' => '#C0223F', 'noteBorder' => '#E6A3AE', 'noteIcon' => '514', 'noteWarn' => false, 'noteMove' => true];
  $withNote = function(array $obj, string $kind, string $text) use ($WARN, $MOVE): array{
    return array_merge($obj, ['hasNote' => true, 'note' => $text], $kind === 'warn' ? $WARN : $MOVE);
  };

  $me = $d[$key];
  $rm = $roleMeta[$key];
  $timesStr = $key === 'n' ? ($me['startStr'] . ' → ' . $me['endStr']) : ($me['startStr'] . ' – ' . $me['endStr']);

  if ($key === 'n'){
    $takeoverPeople = [];
    if ($d['d'] !== null) $takeoverPeople[] = $P($d['d'], 'd');
    if ($d['k'] !== null) $takeoverPeople[] = $P($d['k'], 'k');
    $takeover = array_merge(['time' => $me['startStr'], 'when' => 'Večer', 'people' => $takeoverPeople], $noNote);
    if ($me['preKind'] === 'no'){
      $takeover = $withNote($takeover, 'warn', 'Nastupuješ bez převzetí — denní služba na 524 už skončila. Mezičas do tvého příchodu kryl kolega na 514.');
    }

    $alongside = ['show' => false, 'people' => []];

    $nd = $days[$gi + 1] ?? null;
    $handoffPeople = [];
    if ($nd !== null){
      if ($nd['d'] !== null) $handoffPeople[] = $P($nd['d'], 'd');
      if ($nd['k'] !== null) $handoffPeople[] = $P($nd['k'], 'k');
    }
    $handoff = array_merge(['time' => $me['endStr'], 'when' => 'Ráno · další den', 'people' => $handoffPeople], $noNote);
    if ($me['postKind'] === 'no'){
      $handoff = $withNote($handoff, 'warn', 'Odcházíš bez předávky — ranní denní služba na 524 nastupuje až po tvém odchodu. Mezičas kryje kolega na 514.');
    }
  } else {
    $prev = $gi > 0 ? ($days[$gi - 1] ?? null) : null;
    $takeoverPeople = ($prev !== null && $prev['n'] !== null) ? [$P($prev['n'], 'n')] : [];
    $takeover = array_merge(['time' => $me['startStr'], 'when' => 'Ráno', 'people' => $takeoverPeople], $noNote);
    if ($me['preKind'] === '514'){
      $takeover = $withNote($takeover, 'move', 'Začni na sousedním postu 514. Jakmile v ' . $d['d']['startStr'] . ' dorazí ' . $d['d']['person'] . ' (514), přesedni na svůj post 524.');
    } elseif ($me['preKind'] === 'no'){
      $takeover = $withNote($takeover, 'warn', 'Nastupuješ bez převzetí — předchozí služba na 524 (noční) už skončila. Mezičas do tvého příchodu kryje kolega na 514.');
    }

    $ok = $key === 'd' ? 'k' : 'd';
    $alongside = ['show' => true, 'people' => $d[$ok] !== null ? [$P($d[$ok], $ok)] : []];

    $handoff = array_merge(['time' => $me['endStr'], 'when' => 'Večer', 'people' => $d['n'] !== null ? [$P($d['n'], 'n')] : []], $noNote);
    if ($me['postKind'] === '514'){
      $handoff = $withNote($handoff, 'move', 'Až ve ' . $d['n']['startStr'] . ' dorazí ' . $d['n']['person'] . ' na noční, přesedni na sousední post 514 a dokonči tam směnu do ' . $me['endStr'] . '.');
    } elseif ($me['postKind'] === 'no'){
      $handoff = $withNote($handoff, 'warn', 'Odcházíš bez předávky — noční služba na 524 začíná až po tvém odchodu. Mezičas kryje kolega na 514.');
    }
  }

  return [
    'dateStr' => $d['date'], 'weekday' => $d['weekday'],
    'me' => ['label' => $rm['label'], 'color' => $rm['color'], 'bg' => $rm['bg'], 'timesStr' => $timesStr],
    'takeover' => $takeover, 'alongside' => $alongside, 'handoff' => $handoff,
  ];
}


function renderPrintHeaderBar(string $title, string $subtitle, string $backHref = 'index.php', string $backLabel = '← Zpět do aplikace'): string{
  return '
  <div class="print-header no-print">
    <div>
      <div class="print-title">' . htmlspecialchars($title) . '</div>
      <div class="print-subtitle">' . htmlspecialchars($subtitle) . '</div>
    </div>
    <div class="print-actions">
      <a href="' . htmlspecialchars($backHref) . '">' . htmlspecialchars($backLabel) . '</a>
      <button onclick="window.print()">Vytisknout</button>
    </div>
  </div>';
}


function renderPrintCell(?array $entry, string $roleKey): string{
  if ($entry === null){
    return '<span class="print-empty">—</span>';
  }

  $html = '<b>' . htmlspecialchars($entry['person']) . '</b><br>';
  $html .= '<span style="color:' . $entry['startColor'] . '">' . htmlspecialchars($entry['startStr']) . '</span>–';
  $html .= '<span style="color:' . $entry['endColor'] . '">' . htmlspecialchars($entry['endStr']) . '</span>';

  if ($roleKey === 'k'){
    if ($entry['preKind'] === '514'){
      $html .= '<br><span class="tag-514">514 na začátku</span>';
    }
    if ($entry['postKind'] === '514'){
      $html .= '<br><span class="tag-514">514 na konci</span>';
    }
  }

  if ($roleKey === 'k' || $roleKey === 'n'){
    if ($entry['preKind'] === 'no'){
      $html .= '<br><span class="tag-no">⊘ bez převzetí</span>';
    }
    if ($entry['postKind'] === 'no'){
      $html .= '<br><span class="tag-no">⊘ bez předávky</span>';
    }
  }

  return $html;
}


function renderPrintComplete(array $model, string $rangeLabel): string{
  $html = renderPrintHeaderBar('Rozpis služeb — tiskové zobrazení', 'úsporná tabulka, vhodná pro tisk · ' . $rangeLabel);

  foreach (groupDaysByMonth($model['dates']) as $group){
    $html .= '<table class="sheet"><caption>' . htmlspecialchars($group['label']) . '</caption>';
    $html .= '<thead><tr><th>Datum</th><th>Dlouhá 514</th><th>Krátká 524</th><th>Noční</th></tr></thead><tbody>';

    foreach ($group['days'] as $day){
      $rowClass = $day['weekend'] ? ' class="weekend"' : '';
      $html .= '<tr' . $rowClass . '>';
      $html .= '<td><b>' . htmlspecialchars($day['weekday']) . '</b> ' . htmlspecialchars($day['date']) . '</td>';
      $html .= '<td>' . renderPrintCell($day['d'], 'd') . '</td>';
      $html .= '<td>' . renderPrintCell($day['k'], 'k') . '</td>';
      $html .= '<td>' . renderPrintCell($day['n'], 'n') . '</td>';
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';
  }

  $html .= '<p class="print-footnote"><b>Vysvětlivky:</b> „514 na začátku / na konci“ = pracovník z postu 524 tráví začátek/konec směny na sousedním postu 514. „⊘ bez převzetí / bez předávky“ = na postu 524 vzniká mezera, kterou pokrývá kolega na 514.</p>';

  return $html;
}


function renderPrintWorker(array $model, string $personName, string $rangeLabel, string $personId): string{
  $backHref = 'index.php?person=' . urlencode($personId);
  $html = renderPrintHeaderBar('Rozpis služeb — ' . $personName, 'tiskové zobrazení · klikněte na řádek pro detail střídání · ' . $rangeLabel, $backHref);

  $html .= '<table class="sheet"><thead><tr><th>Datum</th><th>Služba</th><th>Čas</th><th>Poznámky ke střídání</th><th class="no-print"></th></tr></thead><tbody>';

  $roleLabels = ['d' => 'Dlouhá 514', 'k' => 'Krátká 524', 'n' => 'Noční'];

  foreach ($model['dates'] as $gi => $day){
    foreach ($roleLabels as $roleKey => $roleLabel){
      $entry = $day[$roleKey];

      if ($entry === null || $entry['person'] !== $personName){
        continue;
      }

      $notes = [];

      if ($roleKey === 'k'){
        if ($entry['preKind'] === '514') $notes[] = 'Začátek na postu 514';
        if ($entry['postKind'] === '514') $notes[] = 'Konec na postu 514';
      }

      if ($roleKey === 'k' || $roleKey === 'n'){
        if ($entry['preKind'] === 'no') $notes[] = 'Nástup bez převzetí';
        if ($entry['postKind'] === 'no') $notes[] = 'Odchod bez předávky';
      }

      $detailHref = 'index.php?print=worker&person=' . urlencode($personId) . '&shift=' . urlencode($gi . $roleKey);
      $rowClass = $day['weekend'] ? ' class="weekend row-link"' : ' class="row-link"';
      $html .= '<tr' . $rowClass . ' onclick="location.href=&quot;' . htmlspecialchars($detailHref, ENT_QUOTES) . '&quot;">';
      $html .= '<td><b>' . htmlspecialchars($day['weekday']) . '</b> ' . htmlspecialchars($day['date']) . '</td>';
      $html .= '<td>' . htmlspecialchars($roleLabel) . '</td>';
      $html .= '<td><span style="color:' . $entry['startColor'] . '">' . htmlspecialchars($entry['startStr']) . '</span>–<span style="color:' . $entry['endColor'] . '">' . htmlspecialchars($entry['endStr']) . '</span></td>';
      $html .= '<td>' . ($notes ? htmlspecialchars(implode(' · ', $notes)) : '—') . '</td>';
      $html .= '<td class="no-print row-link__chevron">›</td>';
      $html .= '</tr>';
    }
  }

  $html .= '</tbody></table>';

  return $html;
}


function renderPrintPP(array $pp): string{
  return '<div class="pdetail-pp" style="border-left-color:' . $pp['color'] . '; background:' . $pp['bg'] . ';">'
    . '<div class="pdetail-pp__top"><span class="pdetail-pp__name">' . htmlspecialchars($pp['name']) . '</span>'
    . '<span class="pdetail-pp__role" style="color:' . $pp['color'] . ';">' . htmlspecialchars($pp['role']) . '</span></div>'
    . '<div class="pdetail-pp__times"><span style="color:' . $pp['startColor'] . ';">' . htmlspecialchars($pp['startStr']) . '</span>'
    . ' ' . $pp['sep'] . ' '
    . '<span style="color:' . $pp['endColor'] . ';">' . htmlspecialchars($pp['endStr']) . '</span></div>'
    . '</div>';
}


function renderPrintDetailCard(string $badgeLabel, string $badgeColor, string $badgeBg, array $section): string{
  $html = '<div class="pdetail-card">';
  $html .= '<div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; flex-wrap:wrap;">';
  $html .= '<span class="pdetail-badge" style="color:' . $badgeColor . '; background:' . $badgeBg . ';">' . $badgeLabel . '</span>';
  $html .= '<span class="pdetail-when">' . htmlspecialchars($section['when']) . ' · ' . htmlspecialchars($section['time']) . '</span>';
  $html .= '</div>';

  if ($section['hasNote']){
    $html .= '<div class="pdetail-note" style="background:' . $section['noteBg'] . '; border-color:' . $section['noteBorder'] . '; color:' . $section['noteFg'] . ';">';
    $html .= '<span class="pdetail-note__tag" style="color:' . $section['noteFg'] . '; border-color:' . $section['noteBorder'] . ';">' . ($section['noteMove'] ? '514' : '⊘') . '</span>';
    $html .= htmlspecialchars($section['note']);
    $html .= '</div>';
  }

  if (empty($section['people'])){
    $html .= '<span class="print-empty">—</span>';
  } else {
    foreach ($section['people'] as $pp){
      $html .= renderPrintPP($pp);
    }
  }

  $html .= '</div>';

  return $html;
}


function renderPrintDetail(array $detail, string $personName, string $personId, string $rangeLabel): string{
  $backHref = 'index.php?print=worker&person=' . urlencode($personId);
  $html = renderPrintHeaderBar('Detail střídání — ' . $personName, $detail['weekday'] . ' · ' . $detail['dateStr'] . ' · ' . $rangeLabel, $backHref, '← Zpět na Rozpis služeb');

  $html .= '<div class="pdetail">';
  $html .= '<div class="pdetail-me" style="border-left-color:' . $detail['me']['color'] . '; background:' . $detail['me']['bg'] . ';">';
  $html .= '<div class="pdetail-me__date">' . htmlspecialchars($detail['weekday']) . ' · ' . htmlspecialchars($detail['dateStr']) . '</div>';
  $html .= '<div class="pdetail-me__role" style="color:' . $detail['me']['color'] . ';">' . htmlspecialchars($detail['me']['label']) . '</div>';
  $html .= '<div class="pdetail-me__times">' . htmlspecialchars($detail['me']['timesStr']) . '</div>';
  $html .= '</div>';

  $html .= renderPrintDetailCard('↓ PŘEBÍRÁŠ', '#0E7C66', '#DCF2EB', $detail['takeover']);

  if ($detail['alongside']['show']){
    $html .= '<div class="pdetail-card"><div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">';
    $html .= '<span class="pdetail-badge" style="color:#1B5FBF; background:#E1ECFC;">↔ SPOLU VE SLUŽBĚ</span></div>';
    foreach ($detail['alongside']['people'] as $pp){
      $html .= renderPrintPP($pp);
    }
    $html .= '</div>';
  }

  $html .= renderPrintDetailCard('↑ PŘEDÁVÁŠ', '#9A5B00', '#FBEAD0', $detail['handoff']);
  $html .= '</div>';

  return $html;
}


function renderPrintPage(string $bodyHtml): void{
  echo '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rozpis služeb — tisk</title>
  <style>
    @page { size: A4 portrait; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: "Source Sans 3", Arial, sans-serif; margin: 0; padding: 16px; color: #1B2530; }
    .print-header { display:flex; justify-content:space-between; align-items:center; background:#12181F; color:#fff; padding:16px 20px; border-radius:10px; margin-bottom:20px; }
    .print-title { font-size:18px; font-weight:700; }
    .print-subtitle { font-size:13px; color:#B7C0CA; margin-top:2px; }
    .print-actions { display:flex; gap:14px; align-items:center; }
    .print-actions a { color:#fff; text-decoration:none; font-size:14px; }
    .print-actions button { all:unset; cursor:pointer; background:#EB0037; color:#fff; font-weight:700; padding:9px 18px; border-radius:8px; font-size:14px; }
    table.sheet { width:100%; border-collapse:collapse; margin-bottom:24px; page-break-inside:auto; }
    table.sheet caption { text-align:left; font-weight:700; font-size:15px; margin-bottom:6px; caption-side:top; }
    table.sheet th, table.sheet td { border:1px solid #E9EDF1; padding:6px 8px; font-size:12px; text-align:left; vertical-align:top; }
    table.sheet thead th { background:#ECEFF2; }
    table.sheet tr.weekend { background:#FFF7EC; }
    .print-empty { color:#B7C0CA; }
    .tag-514 { display:inline-block; border:1px solid #E6A3AE; color:#C0223F; background:#FBE1E5; font-size:10px; font-weight:700; padding:1px 5px; border-radius:5px; }
    .tag-no { display:inline-block; color:#8A5A00; font-size:10px; font-weight:700; }
    .print-footnote { font-size:11px; color:#4B5563; margin-top:12px; }
    tr.row-link { cursor:pointer; }
    tr.row-link:hover { background:#F5F7F9; }
    tr.row-link:hover.weekend { background:#FFF0DA; }
    .row-link__chevron { color:#8A96A2; font-weight:700; text-align:center; width:20px; }
    .pdetail { display:flex; flex-direction:column; gap:12px; max-width:480px; }
    .pdetail-me { border-left:4px solid; border-radius:8px; padding:12px 14px; }
    .pdetail-me__date { font-size:12px; color:#4B5563; font-weight:600; }
    .pdetail-me__role { font-size:15px; font-weight:700; margin-top:2px; }
    .pdetail-me__times { font-size:13px; color:#1B2530; margin-top:2px; }
    .pdetail-card { border:1px solid #E9EDF1; border-radius:8px; padding:12px 14px; }
    .pdetail-badge { font-size:11px; font-weight:700; padding:3px 8px; border-radius:5px; }
    .pdetail-when { font-size:12px; color:#4B5563; }
    .pdetail-note { border:1px solid; border-radius:6px; padding:7px 9px; font-size:12px; margin-bottom:8px; display:flex; gap:6px; align-items:flex-start; }
    .pdetail-note__tag { flex-shrink:0; font-size:10px; font-weight:700; background:#fff; border:1px solid; padding:1px 5px; border-radius:4px; }
    .pdetail-pp { border-left:3px solid; border-radius:6px; padding:6px 9px; margin-top:6px; }
    .pdetail-pp:first-of-type { margin-top:0; }
    .pdetail-pp__top { display:flex; justify-content:space-between; gap:8px; font-size:13px; }
    .pdetail-pp__name { font-weight:700; }
    .pdetail-pp__role { font-size:11px; font-weight:600; }
    .pdetail-pp__times { font-size:12px; color:#1B2530; margin-top:1px; }
    @media print {
      .no-print { display:none !important; }
      table.sheet { border:none !important; }
      thead { display: table-header-group; }
      tr { break-inside: avoid; }
    }
  </style>
  </head><body>' . $bodyHtml . '</body></html>';
}


$config = new Config("config.cfg");

$monthShiftsListUrl = 'https://docs.google.com/spreadsheets/d/1ysbi-0T4SiMJxXUC3TZRgq263Q7QJO73RvLUdl3s1Lk/export?format=csv&gid=303224713';
$arrayMap = array_map('str_getcsv_26', file($monthShiftsListUrl));

$monthShiftsList = new MonthShiftsList($arrayMap);
$model = $monthShiftsList->buildDesignModel();

$rangeLabel = ($monthShiftsList->datesBetween->from && $monthShiftsList->datesBetween->to)
  ? formatMonthRangeLabel($monthShiftsList->datesBetween->from, $monthShiftsList->datesBetween->to)
  : '';

$printParam = $_GET['print'] ?? null;
$personIdParam = $_GET['person'] ?? null;
$personNameParam = null;

if ($personIdParam !== null && isset($names[$personIdParam]) && $names[$personIdParam] !== ''){
  $personNameParam = $names[$personIdParam];
}

if ($printParam === 'complete'){
  renderPrintPage(renderPrintComplete($model, $rangeLabel));
  exit;
}

if ($printParam === 'worker'){
  if ($personNameParam === null){
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Chybí nebo neplatný parametr person.';
    exit;
  }

  $shiftParam = $_GET['shift'] ?? null;

  if ($shiftParam !== null && preg_match('/^(\d+)([dkn])$/', $shiftParam, $shiftMatches)){
    $shiftGi = (int)$shiftMatches[1];
    $shiftKey = $shiftMatches[2];
    $shiftDay = $model['dates'][$shiftGi] ?? null;

    if ($shiftDay !== null && $shiftDay[$shiftKey] !== null && $shiftDay[$shiftKey]['person'] === $personNameParam){
      $detail = buildDetailModel($model, $shiftGi, $shiftKey);
      renderPrintPage(renderPrintDetail($detail, $personNameParam, $personIdParam, $rangeLabel));
      exit;
    }

    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Neplatný parametr shift.';
    exit;
  }

  renderPrintPage(renderPrintWorker($model, $personNameParam, $rangeLabel, $personIdParam));
  exit;
}

$initialView = $personNameParam !== null ? 'calendar' : 'matrix';

$personIdsForClient = $names;
unset($personIdsForClient['']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rozpis služeb</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css?v=<?= filemtime(__DIR__ . '/assets/app.css') ?>">
</head>
<body>
  <div id="app"></div>
  <script>
    window.SHIFT_DATA = <?= json_encode($model, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    window.APP_CONFIG = <?= json_encode([
      'rangeLabel' => $rangeLabel,
      'personIds' => $personIdsForClient,
      'initialView' => $initialView,
      'initialPerson' => $personNameParam,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  </script>
  <script src="assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</body>
</html>
