<?php

class CryptoZoonFarmer
{
    private $hashRate = 0;
    private $totalHashRate;
    private $poolDailyReward = 1788500;
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

    public function __construct(array $zoans, float $zoon, int $hashRate, float $zoonPrice) {
        $this->addZoans($zoans);

        $this->totalHashRate = $hashRate;
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
                ];

                $incomePeriod = 0;

                $this->zoonToUSD -= $this->zoonToUSD * $inflation;
            }
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
        echo "Final zoon price: " . $this->zoonToUSD . " USD<br><br>";


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
    </tr>
HTML;
        }

        echo <<<HTML
</table>
HTML;

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

$redis = new Redis();

$redis->connect('redis');
$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);

if (!empty($_POST)) {
    $oldHashRate = $redis->get('zoon_hashrate');
    $redis->set('zoon_hashrate', $_POST['hashrate']);

    # Store changes in hashrate with date they were registered
    if($oldHashRate !== $_POST['hashrate']) {
        $redis->hset('zoon_hashrate_historical', date('Y-m-d H:i:s'), $_POST['hashrate']);
    }

    $redis->set('zoan_price', $_POST['zoan_price']);

    $zoans = Zoan::makeMulti(2, 1, 2, 2000);
    $zoans = array_merge($zoans, Zoan::makeMulti(25, 1, 3, 1800));
    $zoans[] = new Zoan(2, 4, 3800);

    $zoanToPurchase = new Zoan((int)$_POST['zoan_rarity'], (int)$_POST['zoan_level'], (int)$_POST['zoan_price']);

    $farmer = new CryptoZoonFarmer($zoans, (float)$_POST['start_zoon'], $_POST['hashrate'], PancakeSwap::getPrice());
    $farmer->executeStrategy(
        (int)$_POST['period'], $zoanToPurchase, (int)$_POST['purchaseInterval'],
        $_POST['purchaseInterval'] * $_POST['decay'] / 100.0
    );
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
    <input type="text" name="hashrate" value="<?= $redis->get('zoon_hashrate') ?>"/><br>
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
    <input name="zoan_price" value="<?= $redis->get('zoan_price') ?>" /><br>
    <input type="submit"/>
</form>
<?php
if (!empty($_POST)) {
    $farmer->outputAsTable(CryptoZoonFarmer::$DKK);
}
?>

</body>
</html>