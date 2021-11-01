<?php

class CryptoZoonFarmer
{
    private $hashRate = 0;
    private $totalHashRate;
    private $hashRateDailyIncrease;
    private $poolDailyReward = 1822500; // 2.001.902
    private $zoans;
    private $zoon;
    private $zoonToUSD;
    private static $USDToEUR = 0.87;
    private static $USDToDKK = 6.45;
    private $results = [];
    private $investment = 0;
    private $initialInvestment;
    private $initialHashRate;

    public static $USD = 'USD';
    public static $EUR = 'EUR';
    public static $DKK = 'DKK';

    public function __construct(array $zoans, float $zoon, int $hashRate, float $zoonPrice, int $hashRateDailyIncrease = 0) {
        $this->addZoans($zoans);

        $this->totalHashRate = $hashRate;
        $this->hashRateDailyIncrease = $hashRateDailyIncrease;
        $this->zoonToUSD = $zoonPrice;
        $this->zoon = $zoon;

        $this->initialHashRate = $this->hashRate;
        $this->initialInvestment = $this->toCurrency($this->investment, $this->zoonToUSD, self::$USDToDKK);
    }

    public function addZoan(Zoan $zoan, int $amount = 1): void {
        for ($i = 0; $i < $amount; $i++) {
            $this->zoans[] = clone $zoan;
            $this->hashRate += $zoan->hashRate();
            $this->investment += $zoan->price();
            $this->zoon -= $zoan->price();
        }
    }

    private function addZoans(array $zoans): void
    {
        foreach ($zoans as $zoan) {
            $this->zoans[] = $zoan;
            $this->hashRate += $zoan->hashRate();
            $this->investment += $zoan->price();
        }
    }

    private function toCurrency(float $zoon, float $zoonPrice, $currency): float {

        $usdPrice = $zoon * $zoonPrice;

        if ($currency === self::$USD) {
            return round($usdPrice, 2);
        }

        if ($currency === self::$EUR) {
            return round($usdPrice * self::$USDToEUR, 2);
        }

        return round($usdPrice * self::$USDToDKK, 2);
    }

    public function farm(): float
    {
        $ratio = $this->hashRate / $this->totalHashRate;

        $reward = $ratio * $this->poolDailyReward;

        $this->zoon += $reward;

        return $reward;
    }

    public function zoon(): float
    {
        return round($this->zoon, 2);
    }

    public function executeStrategy(int $days, Zoan $zoan, int $purchaseInterval = 1, float $inflation = 0): void {

        $incomePeriod = 0;

        for ($day = 1; $day <= $days; $day++) {

            $zoansAdded = 0;

            if($day % $purchaseInterval === 0) {

                # Buy if possible
                while ($zoan->price() <= $this->zoon) {
                    $this->addZoan($zoan);
                    $zoansAdded++;
                }
            }

            $income = $this->farm();
            $incomePeriod += $income;

            if ($zoansAdded > 0 || $day === 1) {
                $this->results[] = [
                    'day' => $day,
                    'income' => round($income, 2),
                    'income_period' => round($incomePeriod, 2),
                    'zoans_purchased' => $zoansAdded,
                    'zoans_purchased_total' => count($this->zoans),
                    'zoonPrice' => $this->zoonToUSD,
                    'total_hash_rate' => $this->totalHashRate,
                ];

                $incomePeriod = 0;

                $this->zoonToUSD -= $this->zoonToUSD * $inflation;
            }

            # Increase the hashrate with the average daily increase of all logged previous entries
            $this->totalHashRate += $this->hashRateDailyIncrease;
        }
    }

    public function outputAsTable(string $currency): void
    {
        echo "Initial investment: " . $this->initialInvestment . " $currency<br>";
        echo "Total investment: " . number_format($this->toCurrency($this->investment, $this->zoonToUSD, $currency), 2)
            . " $currency<br>";
        echo "Initial hash rate: " . number_format($this->initialHashRate) . "<br>";
        echo "Final hash rate: " . number_format($this->hashRate) . "<br>";
        echo "Initial zoon price " . PancakeSwap::getPrice() . " USD<br>";
        echo "Final zoon price: " . $this->zoonToUSD . " USD<br>";
        echo "Start date: " . date('Y-m-d') . '<br><br>';


        echo <<<HTML
<style>
  th { padding-right: 20px;}
  td { text-align: right;}
</style>
<table>
    <tr>
        <th>Day</th>
        <th>Income day</th>
        <th>Income period</th>
        <th>Income period $currency</th>
        <th>Zoans purchased</th>
        <th>Zoans purchased total</th>
        <th>Total hashrate</th>
    </tr>
HTML;


        foreach ($this->results as $result) {

            echo <<<HTML
   <tr>
        <td>{$result['day']}</td>
        <td>{$result['income']}</td>
        <td>{$result['income_period']}</td>
        <td>{$this->toCurrency($result['income_period'], $result['zoonPrice'], $currency)}</td>
        <td>{$result['zoans_purchased']}</td>
        <td>{$result['zoans_purchased_total']}</td>
        <td>{$result['total_hash_rate']}</td>
    </tr>
HTML;
        }

        echo <<<HTML
</table>
HTML;

    }

    public function getTotalHashRate() : int {
        return $this->totalHashRate;
    }
}

class Zoan
{
    private $rarity;
    private $level;
    private $hashRate;
    private $price;

    public function __construct(
        int $rarity,
        int $level,
        int $price
    ) {
        $this->rarity = $rarity;
        $this->price = $price;

        $this->level = $level;
        $this->hashRate = $this->calculateHashRate($this->level);
    }

    private function calculateHashRate(int $level): int
    {

        if ($level === 1) {
            return 0;
        }

        if ($this->rarity > 2 && $level === 2) {
            return 0;
        }

        $basePower = [
            1 => 200,
            2 => 300,
            3 => 400,
            4 => 500,
            5 => 600,
            6 => 700
        ];

        $multiplier = [
            1 => 75,
            2 => 70,
            3 => 65,
            4 => 60,
            5 => 55,
            6 => 50
        ];

        return $basePower[$this->rarity] * $multiplier[$this->rarity] * ($this->level - 1);
    }

    public static function makeMulti(
        int $amount,
        int $rarity,
        int $level,
        int $price
    ): array {
        $zoans = [];

        for ($i = 0; $i < $amount; $i++) {
            $zoans[] = new self($rarity, $level, $price);
        }

        return $zoans;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function hashRate(): int
    {
        return $this->hashRate;
    }

    public function price(): int
    {
        return $this->price;
    }

    public function toString(): string
    {

        $string = "level {$this->level()}" . PHP_EOL;
        $string .= "hash rate {$this->hashRate()}" . PHP_EOL;

        return $string;
    }
}

class PancakeSwap {

    /**
     * @return float
     */
    public static function getPrice(): float
    {
        $url = 'https://api.pancakeswap.info/api/v2/tokens/0x9d173e6c594f479b4d47001f8e6a95a7adda42bc';

        $response = file_get_contents($url);
        try {
            $response = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return -1;
        }


        return (float)$response->data->price;
    }
}

class CoinMarketCap
{

    /**
     * @throws JsonException
     */
    public static function getPrice(): float
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest';
        $parameters = [
            'symbol' => 'zoon',
        ];

        $headers = [
            'Accepts: application/json',
            'X-CMC_PRO_API_KEY: aca0fbe3-8af9-4e55-be68-d64a5eaf8283'
        ];
        $qs = http_build_query($parameters);
        $request = "{$url}?{$qs}";


        $curl = curl_init();
        // Set cURL options
        curl_setopt_array(
            $curl, [
            CURLOPT_URL => $request,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => 1
        ]
        );

        $response = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($response, false, 512, JSON_THROW_ON_ERROR);

        return (float)$response->data->ZOON->quote->USD->price;
    }
}

class HashRate {

    private $days = [];

    public function __construct() {
        $this->loadData();
    }

    public function storeData(int $hashRate) : void {

        $line = date('Y-m-d H:i:s') . "," . $hashRate . PHP_EOL;
        $file = __DIR__ . '/hashRates.csv';

        file_put_contents($file, $line, FILE_APPEND);
    }

    private function loadData(): void {

        $file = __DIR__ . '/hashRates.csv';

        if(!file_exists($file)) {
            file_put_contents($file, '');
        }

        $handle = fopen($file, "rb");

         while (($data = fgetcsv($handle, 1000)) !== FALSE) {
             $this->days[substr($data,0, 10)] = $data[1];
         }
    }

    public function averageDailyChange() : int {

        if(count($this->days) < 2) {
            return 0;
        }

        $dates = array_keys($this->days);
        $rates = array_values($this->days);

        $numberOfDays = $this->dateDiff($dates[0], $dates[count($dates) - 1]);
        $difference = $rates[count($rates) - 1] - $rates[0];

        return round($difference / $numberOfDays);
    }

    public function latestHashRate() : int {
        if(empty($this->days)) {
            return 0;
        }

        $rates = array_values($this->days);

        return $rates[count($rates) - 1];
    }

    private function dateDiff(string $date1, string $date2): int {
        $date1_ts = strtotime($date1);
        $date2_ts = strtotime($date2);
        $diff = $date2_ts - $date1_ts;

        return round($diff / 86400);
    }

    public function makeGraph($img_width = 450, $img_height = 300, $margins = 20) {
        # ------- The graph values in the form of associative array
        $values= $this->days;

        # ---- Find the size of graph by subtracting the size of borders
        $graph_width=$img_width - $margins * 2;
        $graph_height=$img_height - $margins * 2;
        $img=imagecreate($img_width,$img_height);


        $bar_width=20;
        $total_bars=count($values);
        $gap= ($graph_width- $total_bars * $bar_width ) / ($total_bars +1);

        # -------  Define Colors ----------------
        $bar_color=imagecolorallocate($img,0,64,128);
        $background_color=imagecolorallocate($img,240,240,255);
        $border_color=imagecolorallocate($img,200,200,200);
        $line_color=imagecolorallocate($img,220,220,220);

        # ------ Create the border around the graph ------

        imagefilledrectangle($img,1,1,$img_width-2,$img_height-2,$border_color);
        imagefilledrectangle($img,$margins,$margins,$img_width-1-$margins,$img_height-1-$margins,$background_color);


        # ------- Max value is required to adjust the scale -------
        $max_value=max($values);
        $ratio= $graph_height/$max_value;


        # -------- Create scale and draw horizontal lines  --------
        $horizontal_lines=20;
        $horizontal_gap=$graph_height/$horizontal_lines;

        for($i=1;$i<=$horizontal_lines;$i++){
            $y=$img_height - $margins - $horizontal_gap * $i ;
            imageline($img,$margins,$y,$img_width-$margins,$y,$line_color);
            $v= (int)($horizontal_gap * $i / $ratio);
            imagestring($img,0,5,$y-5,$v,$bar_color);

        }


        # ----------- Draw the bars here ------
        for($i=0;$i< $total_bars; $i++){
            # ------ Extract key and value pair from the current pointer position
            [$key, $value] = each($values);
            $x1= $margins + $gap + $i * ($gap+$bar_width) ;
            $x2= $x1 + $bar_width;
            $y1=$margins +$graph_height- (int)($value * $ratio);
            $y2=$img_height-$margins;
            imagestring($img,0,$x1+3,$y1-10,$value,$bar_color);imagestring($img,0,$x1+3,$img_height-15,$key,$bar_color);
            imagefilledrectangle($img,$x1,$y1,$x2,$y2,$bar_color);
        }
        header("Content-type:image/png");
        imagepng($img);
    }
}

$hashRate = new HashRate();

if (!empty($_POST)) {
    $oldHashRate = $hashRate->latestHashRate();

    # Store changes in hashrate with date they were registered
    if($oldHashRate !== $_POST['hashrate']) {
        $hashRate->storeData((int)$_POST['hashrate']);
    }

    $zoans = Zoan::makeMulti(3, 1, 2, 800);
    $zoans = array_merge($zoans, Zoan::makeMulti(34, 1, 3, 1100));
    $zoans[] = new Zoan(2, 4, 2600);

    $zoanToPurchase = new Zoan((int)$_POST['zoan_rarity'], (int)$_POST['zoan_level'], (int)$_POST['zoan_price']);

    $price = PancakeSwap::getPrice();

    if ($price < 0) {
        $price = CoinMarketCap::getPrice();
    }

    $farmer = new CryptoZoonFarmer($zoans, (float)$_POST['start_zoon'], $_POST['hashrate'], $price, $_POST['hash_rate_rise']);
    $farmer->executeStrategy(
        (int)$_POST['period'], $zoanToPurchase, (int)$_POST['purchaseInterval'],
        $_POST['purchaseInterval'] * $_POST['decay'] / 100.0
    );
}

if($_GET['hashrate']) {
    $rate = new HashRate();
    $rate->makeGraph(1024, 768);
    exit;
}

?>
<html lang="en">
<head>
    <title>Crypto zoon calculator</title></head>
<body>
<form method="post" action="/_custom/cryptozoon/zoon.php">
    Start zoon<br>
    <input name="start_zoon" value="<?= $_POST['start_zoon'] ?: '0' ?>"><br>
    Hash rate<br>
    <input type="text" name="hashrate" value="<?= $hashRate->latestHashRate() ?>"/><br>
    Period<br>
    <input name="period" value="<?= $_POST['period'] ?: 180 ?>" /><br>
    Purchase interval<br>
    <input name="purchaseInterval" value="<?= $_POST['purchaseInterval'] ?: 1 ?>" /><br>
    Daily zoon price decay percentage<br>
    <input name="decay" value="<?= $_POST['decay'] ?: '0' ?>" /><br><br>
    Zoan rarity<br>
    <input name="zoan_rarity" value="<?= $_POST['zoan_rarity'] ?: '1'?>" /><br>
    Zoan level<br>
    <input name="zoan_level" value="<?= $_POST['zoan_level'] ?: '3'?>" /><br>
    Zoan price<br>
    <input name="zoan_price" value="<?= $_POST['zoan_price'] ?: 1100 ?>" /><br>
    Hash rate daily rise<br>
    <input type="text" name="hash_rate_rise" value="<?= $_POST['hash_rate_rise'] ?: $hashRate->averageDailyChange() ?>">
    <br><br>
    <input type="submit"/>
</form>
<!--
<img src="/_custom/cryptozoon/zoon.php?hashrate=1" /><br><br>
-->
<?php
if (!empty($_POST)) {
    $farmer->outputAsTable(CryptoZoonFarmer::$DKK);
}


?>

</body>
</html>