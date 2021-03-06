<?php

declare(strict_types=1);

namespace Dinhdjj\CardChargingV2;

use Dinhdjj\CardChargingV2\Data\CardType;
use Dinhdjj\CardChargingV2\Enums\Status;
use Dinhdjj\CardChargingV2\Models\Card;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class CardChargingV2
{
    protected array $connection;

    public function __construct(string|array|null $connection = null)
    {
        if (\is_string($connection) || null === $connection) {
            $connection ??= Config::get('card-charging-v2.default');

            if (!Config::has('card-charging-v2.connections.'.$connection)) {
                throw new InvalidArgumentException("Card Charging V2 connection [{$connection}] isn't defined yet.");
            }

            $connection = Config::get('card-charging-v2.connections.'.$connection);
        }

        if (\is_array($connection)) {
            $this->connection = $connection;
        }
    }

    /**
     * Use specific connection.
     */
    public static function connection(string|array|null $connection = null): self
    {
        return new self($connection);
    }

    /**
     * Fetch card types from thesieure.
     *
     * @throws \Illuminate\Http\Client\RequestException
     *
     * @return \Dinhdjj\CardChargingV2\Data\CardType[]
     */
    public function getFee(): array
    {
        $url = 'https://'.$this->config('domain').'/chargingws/v2/getfee?partner_id='.$this->config('partner_id');
        $response = Http::get($url)
            ->throw()
        ;

        return array_map(fn ($cardType) => new CardType(
            telco: $cardType['telco'],
            value: (int) $cardType['value'],
            fees: (int) $cardType['fees'],
            penalty: (int) $cardType['penalty'],
        ), $response->json());
    }

    public function getCardModel(): string
    {
        return config('card-charging-v2.card.model', Card::class);
    }

    /**
     * Send card to server for charging/approving.
     */
    public function charging(string $telco, int $declaredValue, string $serial, string $code, string $requestId): Card
    {
        $url = 'https://'.$this->config('domain').'/chargingws/v2';
        $res = Http::post($url, [
            'request_id' => $requestId,
            'telco' => $telco,
            'amount' => $declaredValue,
            'serial' => $serial,
            'code' => $code,
            'partner_id' => $this->config('partner_id'),
            'sign' => $this->generateSign($serial, $code),
            'command' => 'charging',
        ])->throw();

        $resData = $res->json();

        return $this->getCardModel()::forceCreate([
            'trans_id' => $resData['trans_id'] ?? null,
            'request_id' => $requestId,
            'amount' => $resData['amount'] ?? null,
            'value' => $resData['value'] ?? null,
            'declared_value' => $declaredValue,
            'telco' => $telco,
            'serial' => $serial,
            'code' => $code,
            'status' => Status::from($resData['status']),
            'message' => $resData['message'],
            'connection' => $this->connection,
        ]);
    }

    /**
     * Send card to server to check/update latest status card.
     */
    public function check(string $telco, int $declaredValue, string $serial, string $code, string $requestId): Card
    {
        $url = 'https://'.$this->config('domain').'/chargingws/v2';
        $res = Http::post($url, [
            'telco' => $telco,
            'amount' => $declaredValue,
            'serial' => $serial,
            'code' => $code,
            'request_id' => $requestId,
            'partner_id' => $this->config('partner_id'),
            'sign' => $this->generateSign($serial, $code),
            'command' => 'check',
        ])->throw();

        $resData = $res->json();

        return $this->getCardModel()::forceCreate([
            'trans_id' => $resData['trans_id'] ?? null,
            'request_id' => $requestId,
            'amount' => $resData['amount'] ?? null,
            'value' => $resData['value'] ?? null,
            'declared_value' => $declaredValue,
            'telco' => $telco,
            'serial' => $serial,
            'code' => $code,
            'status' => Status::from($resData['status']),
            'message' => $resData['message'],
            'connection' => $this->connection,
        ]);
    }

    /** Generate sign used when communicate with service server */
    public function generateSign(string $serial, string $code): string
    {
        return md5($this->config('partner_key').$code.$serial);
    }

    /**
     * Get given config.
     */
    protected function config(string $key): mixed
    {
        return $this->connection[$key];
    }
}
