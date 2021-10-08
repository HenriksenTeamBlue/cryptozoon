<?php

class CryptoZoonFarmer
{
    private $hashRate = 0;
    private $totalHashRate = 2064166400;
    private $poolDailyReward = 1788500;
    private $zoans;
    private $zoon = 0;
    private static $zoonToUSD = 0.01415;
    private static $USDToEUR = 0.87;
    private static $USDToDKK = 6.45;
    private $payout = 0;
    private $results = [];
    private $investment = 0;
    private $initialInvestment;
    private $initialHashRate;

    public static $USD = 'USD';
    public static $EUR= 'EUR';
    public static $DKK = 'DKK';

    public function __construct(array $zoans)
    {
        $this->addZoans($zoans);

        $this->initialHashRate = $this->hashRate;
        $this->initialInvestment = $this->investment;
    }

    public function addZoan(
        Zoan $zoan,
        int $amount = 1
    ): void {
        for ($i = 0; $i < $amount; $i++) {
            $this->zoans[] = clone $zoan;
            $this->hashRate += $zoan->hashRate();
            $this->investment += $zoan->price();
            $this->zoon -= $zoan->price();
        }
    }

    private function addZoans(array $zoans) : void {
        foreach ($zoans as $zoan) {
            $this->zoans[] = $zoan;
            $this->hashRate += $zoan->hashRate();
            $this->investment += $zoan->price();
        }
    }

    private function toCurrency(float $zoon, $currency) : float {
        if($currency === self::$USD) {
            return round($zoon * self::$zoonToUSD, 2);
        }

        if($currency === self::$EUR) {
            return round($zoon * self::$zoonToUSD * self::$USDToEUR, 2);
        }

        return round($zoon * self::$zoonToUSD * self::$USDToDKK, 2);
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

    public function executeStrategy(int $days, Zoan $zoan, float $payoutRatio = 0): void {

        for($i = 1; $i <= $days; $i++) {

            $dailyPayout = $this->performPayout($payoutRatio);

            $zoanAdded = 0;

            # Buy if possible
            while($zoan->price() <= $this->zoon) {
                $this->addZoan($zoan);
                $zoanAdded++;
            }

            $income = $this->farm();

            if ($zoanAdded > 0 || $i === 1) {
                $this->results[] = [
                    'day' => $i,
                    'income' => round($income, 2),
                    'payout' => round($dailyPayout, 2),
                    'payout_total' => $this->payout,
                    'zoans_purchased' => $zoanAdded,
                    'zoans_purchased_total' => count($this->zoans),
                ];
            }
        }
    }

    private function performPayout($ratio): float {
        $payout = $ratio * $this->zoon();

        $this->payout += $payout;
        $this->zoon -= $payout;

        return $payout;
    }

    public function outputAsTable(string $currency): void
    {
        echo "Initial investment: " . number_format($this->toCurrency($this->initialInvestment, $currency), 2) . " $currency<br>";
        echo "Total investment: " . number_format($this->toCurrency($this->investment, $currency), 2)
            . " $currency<br>";
        echo "Initial hash rate: ". number_format($this->initialHashRate) . "<br>";
        echo "Final hash rate: " . number_format($this->hashRate) . "<br><br>";

        echo <<<HTML
<style>
  th { padding-right: 20px;}
  td { text-align: right;}
</style>
<table>
    <tr>
        <th>Day</th>
        <th>Income</th>
        <th>Income $currency</th>
        <th>Zoans purchased</th>
        <th>Zoans purchased total</th>
    </tr>
HTML;


        foreach($this->results as $result) {

            echo <<<HTML
   <tr>
        <td>{$result['day']}</td>
        <td>{$result['income']}</td>
        <td>{$this->toCurrency($result['income'], $currency)}</td>
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
    private $exp;

    public function __construct(
        int $rarity,
        float $exp,
        int $price
    ) {
        $this->rarity = $rarity;
        $this->exp = $exp;
        $this->price = $price;

        $this->level = $this->calculateLevel($exp);
        $this->hashRate = $this->calculateHashRate($this->level);
    }

    private function calculateLevel(float $exp): int
    {

        $levelMap = [
            100 => 2,
            350 => 3,
            1000 => 4,
            2000 => 5,
            4000 => 6
        ];

        $level = 1;

        foreach ($levelMap as $expLimit => $lvl) {
            if ($exp < $expLimit) {
                break;
            }

            $level = $lvl;
        }

        return $level;
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

    public static function makeMulti(int $amount, int $rarity, float $exp, int $price) : array {
        $zoans = [];

        for($i = 0; $i < $amount; $i++) {
            $zoans[] = new self($rarity, $exp, $price);
        }

        return $zoans;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function exp(): float
    {
        return $this->exp;
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
        $string = '';

        $string .= "exp {$this->exp()}" . PHP_EOL;
        $string .= "level {$this->level()}" . PHP_EOL;
        $string .= "hash rate {$this->hashRate()}" . PHP_EOL;

        return $string;
    }
}

$zoans = Zoan::makeMulti(2, 1, 300, 2000);
$zoans = array_merge($zoans, Zoan::makeMulti(24, 1, 400, 1800));
$zoans[] = new Zoan(2, 1000, 3800);

$farmer = new CryptoZoonFarmer($zoans);
$farmer->executeStrategy(180, new Zoan(1, 400, 1800),0.0);
$farmer->outputAsTable(CryptoZoonFarmer::$DKK);