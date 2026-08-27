<?php

declare(strict_types=1);

namespace Daraja\Services;

use Daraja\Enums\SimSubscriberOperation;
use Daraja\Exceptions\ValidationException;
use Daraja\Http\HttpClient;
use Daraja\Http\Response;

/**
 * IoT SIM Management Service.
 *
 * Manages Safaricom IoT SIM cards (activation, suspension, renaming, status
 * checks) and their messaging channel (send/search/filter/delete messages),
 * via the `/simportal/*` product family.
 *
 * ⚠️ Requires the Safaricom IoT SIM Management platform product, purchased
 * separately via https://www.business.safaricom.co.ke/products/IoTSimManagement
 * — this is not unlocked by a standard Daraja/M-Pesa app. You still
 * authenticate with the same OAuth Bearer token as every other Daraja API.
 *
 * Every response uses a `header`/`body` envelope, distinct from the
 * `Result`/flat-field shapes used elsewhere in this SDK — use
 * {@see self::isSuccessful()} and {@see self::message()} rather than
 * `Response::isAccepted()`.
 *
 * Endpoints (all POST, base path `/simportal`):
 *   /v1/allsims               {@see self::getAllSims()}
 *   /v1/queryLifeCycleStatus  {@see self::queryLifeCycleStatus()}
 *   /v1/querycustomerinfo     {@see self::queryCustomerInfo()}
 *   /v1/simactivation         {@see self::activateSim()}
 *   /v1/getactivationtrends   {@see self::getActivationTrends()}
 *   /v1/renameasset           {@see self::renameAsset()}
 *   /v1/suspend_unsuspend_sub {@see self::suspendOrResumeSubscriber()}
 *   /v1/searchmessages        {@see self::searchMessages()}
 *   /v1/filtermessages        {@see self::filterMessages()}
 *   /v1/getallmessages        {@see self::getAllMessages()}
 *   /v1/sendsinglemessage     {@see self::sendSingleMessage()}
 *   /v1/deleteMessageThread   {@see self::deleteMessageThread()}
 *   /v1/deletemessage         {@see self::deleteMessage()}
 *
 * @see https://developer.safaricom.co.ke/apis/IotSimManagement
 */
final class IotSimManagement
{
    private const BASE = '/simportal/v1';

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    // ── SIM Operations ────────────────────────────────────────────────────────

    /**
     * Fetch details of all SIMs under a customer account.
     *
     * @param  string $vpnGroup  Account number, e.g. "1-225560081663_VPN".
     */
    public function getAllSims(
        string $vpnGroup,
        string $username,
        int    $startAtIndex = 0,
        int    $pageSize = 10,
    ): Response {
        $this->requireNonEmpty(['vpnGroup' => $vpnGroup, 'username' => $username]);

        return $this->http->post(self::BASE . '/allsims', [
            'vpnGroup'    => [$vpnGroup],
            'startAtInde' => (string) $startAtIndex, // sic — matches Safaricom's documented (misspelled) field name
            'pageSize'    => (string) $pageSize,
            'username'    => $username,
        ]);
    }

    /** Check a SIM's current lifecycle status (Active, Suspended, etc.). */
    public function queryLifeCycleStatus(string $msisdn, string $vpnGroup, string $username): Response
    {
        $this->requireNonEmpty(['msisdn' => $msisdn, 'vpnGroup' => $vpnGroup, 'username' => $username]);

        return $this->http->post(self::BASE . '/queryLifeCycleStatus', [
            'msisdn'   => $msisdn,
            'vpnGroup' => $vpnGroup,
            'username' => $username,
        ]);
    }

    /** Check SIM and assigned product/tariff status. */
    public function queryCustomerInfo(string $msisdn, string $vpnGroup, string $username): Response
    {
        $this->requireNonEmpty(['msisdn' => $msisdn, 'vpnGroup' => $vpnGroup, 'username' => $username]);

        return $this->http->post(self::BASE . '/querycustomerinfo', [
            'msisdn'   => $msisdn,
            'vpnGroup' => $vpnGroup,
            'username' => $username,
        ]);
    }

    /** Activate a SIM card. */
    public function activateSim(string $msisdn, string $vpnGroup, string $username): Response
    {
        $this->requireNonEmpty(['msisdn' => $msisdn, 'vpnGroup' => $vpnGroup, 'username' => $username]);

        return $this->http->post(self::BASE . '/simactivation', [
            'msisdn'   => $msisdn,
            'vpnGroup' => $vpnGroup,
            'username' => $username,
        ]);
    }

    /**
     * Fetch a trend graph of SIM lifecycle operations (pooled/suspended/active/idle) over a date range.
     *
     * @param  \DateTimeInterface $startDate  Start of the report window.
     * @param  \DateTimeInterface $endDate    End of the report window.
     */
    public function getActivationTrends(
        string             $vpnGroup,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string             $username,
    ): Response {
        $this->requireNonEmpty(['vpnGroup' => $vpnGroup, 'username' => $username]);

        if ($endDate < $startDate) {
            throw new ValidationException(['end_date' => 'End date must not be before the start date']);
        }

        return $this->http->post(self::BASE . '/getactivationtrends', [
            'vpnGroup'  => $vpnGroup,
            'startDate' => $startDate->format('Ymd'),
            'stopDate'  => $endDate->format('Ymd'),
            'username'  => $username,
        ]);
    }

    /** Assign a customer-preferred name to a SIM/asset. */
    public function renameAsset(string $msisdn, string $vpnGroup, string $username, string $assetName): Response
    {
        $this->requireNonEmpty([
            'msisdn'    => $msisdn,
            'vpnGroup'  => $vpnGroup,
            'username'  => $username,
            'assetName' => $assetName,
        ]);

        return $this->http->post(self::BASE . '/renameasset', [
            'msisdn'    => $msisdn,
            'vpnGroup'  => $vpnGroup,
            'username'  => $username,
            'assetName' => $assetName,
        ]);
    }

    /** Suspend or resume a subscriber/SIM on a given product/tariff. */
    public function suspendOrResumeSubscriber(
        string                  $msisdn,
        string                  $username,
        string                  $vpnGroup,
        string                  $product,
        SimSubscriberOperation  $operation,
    ): Response {
        $this->requireNonEmpty([
            'msisdn'   => $msisdn,
            'username' => $username,
            'vpnGroup' => $vpnGroup,
            'product'  => $product,
        ]);

        return $this->http->post(self::BASE . '/suspend_unsuspend_sub', [
            'msisdn'    => $msisdn,
            'username'  => $username,
            'vpnGroup'  => $vpnGroup,
            'product'   => $product,
            'operation' => $operation->value,
        ]);
    }

    // ── Messaging ────────────────────────────────────────────────────────────

    /** Search messages by SIM number (must be prefixed with "254"). */
    public function searchMessages(string $searchValue): Response
    {
        $this->requireNonEmpty(['searchValue' => $searchValue]);

        return $this->http->post(self::BASE . '/searchmessages', [
            'searchValue' => $searchValue,
        ]);
    }

    /** Filter messages by date range and processing status. */
    public function filterMessages(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string             $status,
    ): Response {
        if ($endDate < $startDate) {
            throw new ValidationException(['end_date' => 'End date must not be before the start date']);
        }

        return $this->http->post(self::BASE . '/filtermessages', [
            'startDate' => $startDate->format('d-m-Y H:i:s'),
            'endDate'   => $endDate->format('d-m-Y H:i:s'),
            'status'    => $status,
        ]);
    }

    /** Fetch all messages for an account, paginated. */
    public function getAllMessages(string $vpnGroup, int $pageNo = 1, int $pageSize = 10): Response
    {
        $this->requireNonEmpty(['vpnGroup' => $vpnGroup]);

        return $this->http->post(self::BASE . '/getallmessages', [
            'vpnGroup' => $vpnGroup,
            'pageNo'   => $pageNo,
            'pageSize' => $pageSize,
        ]);
    }

    /** Send a single message to a SIM. */
    public function sendSingleMessage(string $msisdn, string $message, string $vpnGroup): Response
    {
        $this->requireNonEmpty(['msisdn' => $msisdn, 'message' => $message, 'vpnGroup' => $vpnGroup]);

        return $this->http->post(self::BASE . '/sendsinglemessage', [
            'msisdn'   => $msisdn,
            'message'  => $message,
            'vpnGroup' => $vpnGroup,
        ]);
    }

    /** Delete all messages sent to/from a given SIM. */
    public function deleteMessageThread(string $msisdn): Response
    {
        $this->requireNonEmpty(['msisdn' => $msisdn]);

        return $this->http->post(self::BASE . '/deleteMessageThread', [
            'msisdn' => $msisdn,
        ]);
    }

    /** Delete a single message by its database ID. */
    public function deleteMessage(int $id): Response
    {
        if ($id < 1) {
            throw new ValidationException(['id' => 'Message ID must be a positive integer']);
        }

        return $this->http->post(self::BASE . '/deletemessage', [
            'id' => $id,
        ]);
    }

    // ── Response helpers ─────────────────────────────────────────────────────

    /** True when the nested `header.responseCode` indicates success (200). */
    public function isSuccessful(Response $response): bool
    {
        return $this->header($response, 'responseCode') === '200';
    }

    /** Human-readable status message from the nested header. */
    public function message(Response $response): string
    {
        return $this->header($response, 'customerMessage')
            ?: $this->header($response, 'responseMessage');
    }

    private function header(Response $response, string $key): string
    {
        $header = $response->data()['header'] ?? [];

        return is_array($header) ? (string) ($header[$key] ?? '') : '';
    }

    /** @param array<string, string> $fields */
    private function requireNonEmpty(array $fields): void
    {
        $errors = [];

        foreach ($fields as $name => $value) {
            if ($value === '') {
                $errors[$name] = "{$name} is required";
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
