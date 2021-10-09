<?php

class CryptoZoonFarmer
{
    private $hashRate = 0;
    private $totalHashRate;
    private $poolDailyReward = 1788500;
    private $zoans;
    private $zoon = 0;
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

    public function __construct(array $zoans, int $hashRate, float $zoonPrice) {
        $this->addZoans($zoans);

        $this->totalHashRate = $hashRate;
        $this->zoonToUSD = $zoonPrice;

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

            if ($zoansAdded > 0) {
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
        echo "Final zoon price: " . $this->zoonToUSD . "<br><br>";


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

class CoinMarketCap
{

    /**
     * @throws JsonException
     */
    public static function getPrice(string $sybmol): float
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest';
        $parameters = [
            'symbol' => $sybmol,
        ];

        $headers = [
            'Accepts: application/json',
            'X-CMC_PRO_API_KEY: aca0fbe3-8af9-4e55-be68-d64a5eaf8283'
        ];
        $qs = http_build_query($parameters); // query string encode the parameters
        $request = "{$url}?{$qs}"; // create the request URL


        $curl = curl_init(); // Get cURL resource
        // Set cURL options
        curl_setopt_array(
            $curl, [
            CURLOPT_URL => $request,            // set the request URL
            CURLOPT_HTTPHEADER => $headers,     // set the headers
            CURLOPT_RETURNTRANSFER => 1         // ask for raw response instead of bool
        ]
        );

        $response = curl_exec($curl); // Send the request, save the response
        curl_close($curl); // Close request

        $response = json_decode($response, false, 512, JSON_THROW_ON_ERROR);

        return (float)$response->data->ZOON->quote->USD->price;
    }
}

?>
<html lang="en">
<head>
    <title>Crypto zoon calculator</title></head>
<body>
<form method="post" action="/_custom/cryptozoon/zoon.php">
    Hash rate<br>
    <input type="text" name="hashrate" value="<?= $_POST['hashrate'] ?: 2162451200 ?>"/><br>
    Zoon price<br>
    <input type="text" name="zoonPrice" value="<?= $_POST['zoonPrice'] ?: CoinMarketCap::getPrice('zoon') ?>"/><br>
    Period<br>
    <input name="period" value="<?= $_POST['period'] ?: 180 ?>" /><br>
    Purchase interval<br>
    <input name="purchaseInterval" value="<?= $_POST['purchaseInterval'] ?: 5 ?>" /><br>
    Daily zoon price decay percentage<br>
    <input name="decay" value="<?= $_POST['decay'] ?: '0' ?>" /><br>
    <input type="submit"/>
</form>
<?php
if (!empty($_POST)) {
    $zoans = Zoan::makeMulti(2, 1, 3, 2000);
    $zoans = array_merge($zoans, Zoan::makeMulti(24, 1, 3, 1800));
    $zoans[] = new Zoan(2, 4, 3800);

    $farmer = new CryptoZoonFarmer($zoans, $_POST['hashrate'], $_POST['zoonPrice']);
    $farmer->executeStrategy((int)$_POST['period'], new Zoan(1, 3, 1750), (int)$_POST['purchaseInterval'], $_POST['purchaseInterval'] * $_POST['decay'] / 100.0);
    $farmer->outputAsTable(CryptoZoonFarmer::$DKK);
}
?>

</body>
</html>
