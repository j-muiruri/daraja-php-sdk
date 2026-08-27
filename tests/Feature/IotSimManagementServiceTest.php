<?php

declare(strict_types=1);

namespace Daraja\Tests\Feature;

use Daraja\Enums\SimSubscriberOperation;
use Daraja\Exceptions\ValidationException;
use Daraja\Services\IotSimManagement;
use Daraja\Tests\DarajaTestCase;

final class IotSimManagementServiceTest extends DarajaTestCase
{
    private function successHeader(string $message = 'Operation Successfull'): array
    {
        return [
            'requestRefId'    => '9953-4cfa-a173-507eb79891fe237',
            'responseCode'    => 200,
            'responseMessage' => 'Success',
            'customerMessage' => $message,
            'timestamp'       => '2025-03-20T11:22:53.461865400',
        ];
    }

    public function test_get_all_sims_returns_sim_list(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'header' => $this->successHeader(),
                'body'   => ['Desc' => [['life_cycle_status' => 'Active', 'iccid' => '89254014010100030360']]],
            ]],
        ]);

        $service  = new IotSimManagement($http);
        $response = $service->getAllSims(vpnGroup: '1-225560081663_VPN', username: 'user@safaricom.co.ke');

        self::assertTrue($service->isSuccessful($response));
        self::assertSame('Operation Successfull', $service->message($response));
        self::assertNotEmpty($response->get('body'));
    }

    public function test_get_all_sims_throws_when_username_missing(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new IotSimManagement($http);

        $service->getAllSims(vpnGroup: '1-225560081663_VPN', username: '');
    }

    public function test_query_life_cycle_status_returns_active(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'header' => $this->successHeader(),
                'body'   => ['desc' => 'Operation successfull', 'status' => 'Active', 'statusCode' => '0'],
            ]],
        ]);

        $service  = new IotSimManagement($http);
        $response = $service->queryLifeCycleStatus('300000020000', '1-225560081663_VPN', 'user@safaricom.co.ke');

        self::assertTrue($service->isSuccessful($response));
        self::assertSame('Active', $response->data()['body']['status'] ?? null);
    }

    public function test_activate_sim_is_successful(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'header' => $this->successHeader('Line activated successfully'),
                'body'   => ['Desc' => 'Line activated successfully', 'requestId' => 'df19-47d1', 'ID' => '300000130371'],
            ]],
        ]);

        $service  = new IotSimManagement($http);
        $response = $service->activateSim('300000443539', '1-225560081663_VPN', 'user@safaricom.co.ke');

        self::assertTrue($service->isSuccessful($response));
        self::assertSame('Line activated successfully', $service->message($response));
    }

    public function test_get_activation_trends_throws_when_end_before_start(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new IotSimManagement($http);

        $service->getActivationTrends(
            vpnGroup:  '1-225560081663_VPN',
            startDate: new \DateTimeImmutable('2026-04-21'),
            endDate:   new \DateTimeImmutable('2026-02-21'),
            username:  'user@safaricom.co.ke',
        );
    }

    public function test_rename_asset_is_successful(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'header' => $this->successHeader('Asset renamed successfully'),
                'body'   => ['result' => 'Success', 'desc' => 'Operation Successfull'],
            ]],
        ]);

        $service  = new IotSimManagement($http);
        $response = $service->renameAsset('300000038722', '1-225560081663_VPN', 'test@safaricom.co.ke', 'Tracker001');

        self::assertTrue($service->isSuccessful($response));
    }

    public function test_suspend_or_resume_subscriber_sends_correct_operation(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'header' => $this->successHeader(),
                'body'   => ['statusCode' => 0, 'statusDesc' => 'Operation successfully.'],
            ]],
        ]);

        $service  = new IotSimManagement($http);
        $response = $service->suspendOrResumeSubscriber(
            msisdn:    '300000100000',
            username:  'user@safaricom.co.ke',
            vpnGroup:  '1-225560081663_VPN',
            product:   '14205000',
            operation: SimSubscriberOperation::Suspend,
        );

        self::assertTrue($service->isSuccessful($response));
    }

    public function test_search_messages_throws_when_search_value_missing(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new IotSimManagement($http);

        $service->searchMessages('');
    }

    public function test_send_single_message_is_successful(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'header' => $this->successHeader('Message queued successfully.'),
                'body'   => ['id' => 4156, 'message' => 'Test'],
            ]],
        ]);

        $service  = new IotSimManagement($http);
        $response = $service->sendSingleMessage('300001172000', 'Test', '1-47820525000_VPN');

        self::assertTrue($service->isSuccessful($response));
    }

    public function test_delete_message_throws_for_non_positive_id(): void
    {
        $this->expectException(ValidationException::class);

        $config  = $this->makeConfig();
        $http    = $this->makeHttpClientWithMockedToken($config, []);
        $service = new IotSimManagement($http);

        $service->deleteMessage(0);
    }

    public function test_delete_message_is_successful(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'header' => $this->successHeader('Message deleted successfully.'),
                'body'   => null,
            ]],
        ]);

        $service  = new IotSimManagement($http);
        $response = $service->deleteMessage(3888);

        self::assertTrue($service->isSuccessful($response));
    }

    public function test_is_successful_returns_false_for_error_response(): void
    {
        $config = $this->makeConfig();
        $http   = $this->makeHttpClientWithMockedToken($config, [
            ['status' => 200, 'body' => [
                'header' => ['responseCode' => 404, 'customerMessage' => 'No records were found'],
            ]],
        ]);

        $service  = new IotSimManagement($http);
        $response = $service->searchMessages('254300000109264');

        self::assertFalse($service->isSuccessful($response));
        self::assertSame('No records were found', $service->message($response));
    }
}
