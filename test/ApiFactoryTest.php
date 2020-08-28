<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-documentation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-documentation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-documentation/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ApiTools\Documentation;

use Laminas\ApiTools\Configuration\ModuleUtils;
use Laminas\ApiTools\Documentation\ApiFactory;
use Laminas\ApiTools\Documentation\Service;
use Laminas\ApiTools\Provider\ApiToolsProviderInterface;
use Laminas\ModuleManager\ModuleManager;
use PHPUnit\Framework\TestCase;

class ApiFactoryTest extends TestCase
{
    /**
     * @var ApiFactory
     */
    protected $apiFactory;

    protected $expectedStatusCodes = [
        [
            'code' => '200',
            'message' => 'OK',
        ],
        [
            'code' => '201',
            'message' => 'Created',
        ],
        [
            'code' => '204',
            'message' => 'No Content',
        ],
        [
            'code' => '400',
            'message' => 'Client Error',
        ],
        [
            'code' => '401',
            'message' => 'Unauthorized',
        ],
        [
            'code' => '403',
            'message' => 'Forbidden',
        ],
        [
            'code' => '404',
            'message' => 'Not Found',
        ],
        [
            'code' => '406',
            'message' => 'Not Acceptable',
        ],
        [
            'code' => '415',
            'message' => 'Unsupported Media Type',
        ],
        [
            'code' => '422',
            'message' => 'Unprocessable Entity',
        ],
    ];

    protected function setUp()
    {
        $mockModule = $this->prophesize(ApiToolsProviderInterface::class)->reveal();

        $moduleManager = $this->prophesize(ModuleManager::class);
        $moduleManager->getModules()->willReturn(['Test']);
        $moduleManager->getModule('Test')->willReturn($mockModule);

        $moduleUtils = $this->prophesize(ModuleUtils::class);
        $moduleUtils
            ->getModuleConfigPath('Test')
            ->willReturn(__DIR__ . '/TestAsset/module-config/module.config.php');


        $this->apiFactory = new ApiFactory(
            $moduleManager->reveal(),
            include __DIR__ . '/TestAsset/module-config/module.config.php',
            $moduleUtils->reveal()
        );
    }

    public function assertContainsStatusCodes($expectedCodes, $actualCodes, $message = '')
    {
        if (! is_array($expectedCodes)) {
            $expectedCodes = [$expectedCodes];
        }

        $expectedCodePairs = array_filter($this->expectedStatusCodes, function ($code) use ($expectedCodes) {
            if (! is_array($code)) {
                return false;
            }
            if (! array_key_exists('code', $code)) {
                return false;
            }
            if (! in_array($code['code'], $expectedCodes)) {
                return false;
            }

            return true;
        });

        if (empty($expectedCodePairs)) {
            $this->fail(sprintf(
                'No codes provided, or no known codes match: %s',
                var_export($expectedCodes, 1)
            ));
        }

        foreach ($expectedCodePairs as $code) {
            if (! in_array($code, $actualCodes, true)) {
                $this->fail(sprintf(
                    "Failed to find code %s in actual codes:\n%s\n",
                    $code['code'],
                    var_export($actualCodes, 1)
                ));
            }
        }

        return true;
    }

    public function testCreateApiList()
    {
        $apiList = $this->apiFactory->createApiList();
        $this->assertCount(1, $apiList);
        $api = array_shift($apiList);
        $this->assertArrayHasKey('name', $api);
        $this->assertEquals('Test', $api['name']);
        $this->assertArrayHasKey('versions', $api);
        $this->assertInternalType('array', $api['versions']);
        $this->assertEquals(['1'], $api['versions']);
    }

    public function testCreateApi()
    {
        $api = $this->apiFactory->createApi('Test', 1);
        $this->assertInstanceOf('Laminas\ApiTools\Documentation\Api', $api);

        $this->assertEquals('Test', $api->getName());
        $this->assertEquals(1, $api->getVersion());
        $this->assertCount(7, $api->getServices());
    }

    public function testCreateRestService()
    {
        $docConfig = include __DIR__ . '/TestAsset/module-config/documentation.config.php';
        $api = $this->apiFactory->createApi('Test', 1);

        $service = $this->apiFactory->createService($api, 'FooBar');
        $this->assertInstanceOf('Laminas\ApiTools\Documentation\Service', $service);

        $this->assertEquals('FooBar', $service->getName());
        $this->assertEquals($docConfig['Test\V1\Rest\FooBar\Controller']['description'], $service->getDescription());

        $fields = $service->getFields('input_filter');
        $this->assertCount(5, $fields);
        $this->assertInstanceOf('Laminas\ApiTools\Documentation\Field', $fields[0]);
        $this->assertEquals('foogoober/subgoober', $fields[2]->getName());
        $this->assertEquals('foofoogoober/subgoober/subgoober', $fields[3]->getName());

        $ops = $service->getOperations();
        $this->assertCount(2, $ops);

        foreach ($ops as $operation) {
            $this->assertInstanceOf('Laminas\ApiTools\Documentation\Operation', $operation);
            $statusCodes = $operation->getResponseStatusCodes();
            switch ($operation->getHttpMethod()) {
                case 'GET':
                    $this->assertFalse($operation->requiresAuthorization());
                    $this->assertContainsStatusCodes(['406', '415', '200'], $statusCodes);
                    break;
                case 'POST':
                    $this->assertTrue($operation->requiresAuthorization());
                    $this->assertContainsStatusCodes(
                        ['406', '415', '400', '422', '401', '403', '201'],
                        $statusCodes
                    );
                    break;
                default:
                    $this->fail('Unexpected HTTP method encountered: ' . $operation->getHttpMethod());
                    break;
            }
        }

        $eOps = $service->getEntityOperations();
        $this->assertCount(4, $eOps);

        foreach ($eOps as $operation) {
            $this->assertInstanceOf('Laminas\ApiTools\Documentation\Operation', $operation);
            $statusCodes = $operation->getResponseStatusCodes();
            switch ($operation->getHttpMethod()) {
                case 'GET':
                    $this->assertFalse($operation->requiresAuthorization());
                    $this->assertContainsStatusCodes(['406', '415', '404', '200'], $statusCodes);
                    break;
                case 'PATCH':
                case 'PUT':
                    $this->assertTrue($operation->requiresAuthorization());
                    $this->assertContainsStatusCodes(
                        ['406', '415', '400', '422', '401', '403', '200'],
                        $statusCodes
                    );
                    break;
                case 'DELETE':
                    $this->assertTrue($operation->requiresAuthorization());
                    $this->assertContainsStatusCodes(['406', '415', '401', '403', '204'], $statusCodes);
                    break;
                default:
                    $this->fail('Unexpected entity HTTP method encountered: ' . $operation->getHttpMethod());
                    break;
            }
        }
    }

    public function testCreateRestServiceWithCollection()
    {
        $docConfig = include __DIR__ . '/TestAsset/module-config/documentation.config.php';
        $api = $this->apiFactory->createApi('Test', 1);

        $service = $this->apiFactory->createService($api, 'FooBarCollection');
        $this->assertInstanceOf('Laminas\ApiTools\Documentation\Service', $service);

        $this->assertEquals('FooBarCollection', $service->getName());
        $this->assertEquals(
            $docConfig['Test\V1\Rest\FooBarCollection\Controller']['description'],
            $service->getDescription()
        );

        $fields = $service->getFields('input_filter');
        $this->assertCount(2, $fields);

        $this->assertSame('FooBarCollection[]/FooBar', $fields[0]->getName());
        $this->assertSame('AnotherCollection[]/FooBar', $fields[1]->getName());

        $query = array_values($service->getFields('query'));
        $this->assertCount(2, $query);

        $this->assertSame('one', $query[0]->getName());
        $this->assertSame('two', $query[1]->getName());
        $this->assertSame('integer', $query[0]->getType());
        $this->assertSame('string', $query[1]->getType());
    }

    public function testCreateRestArtistsService()
    {
        $docConfig = include __DIR__ . '/TestAsset/module-config/documentation.config.php';

        $api = $this->apiFactory->createApi('Test', 1);

        $service = $this->apiFactory->createService($api, 'Bands');
        self::assertInstanceOf(Service::class, $service);

        self::assertEquals('Bands', $service->getName());
        self::assertEquals(
            $docConfig['Test\V1\Rest\Bands\Controller']['description'],
            $service->getDescription()
        );

        $fields = $service->getFields('input_filter');
        self::assertCount(11, $fields);

        self::assertSame('name', $fields[0]->getName());
        self::assertSame('artists[]/first_name', $fields[1]->getName());
        self::assertSame('artists[]/last_name', $fields[2]->getName());
        self::assertSame('debut_album/title', $fields[3]->getName());
        self::assertSame('debut_album/release_date', $fields[4]->getName());
        self::assertSame('debut_album/tracks[]/number', $fields[5]->getName());
        self::assertSame('debut_album/tracks[]/title', $fields[6]->getName());
        self::assertSame('albums[]/title', $fields[7]->getName());
        self::assertSame('albums[]/release_date', $fields[8]->getName());
        self::assertSame('albums[]/tracks[]/number', $fields[9]->getName());
        self::assertSame('albums[]/tracks[]/title', $fields[10]->getName());
    }

    public function testCreateRpcService()
    {
        $docConfig = include __DIR__ . '/TestAsset/module-config/documentation.config.php';
        $api = $this->apiFactory->createApi('Test', 1);

        $service = $this->apiFactory->createService($api, 'Ping');
        $this->assertInstanceOf('Laminas\ApiTools\Documentation\Service', $service);

        $this->assertEquals('Ping', $service->getName());
        $this->assertEquals($docConfig['Test\V1\Rpc\Ping\Controller']['description'], $service->getDescription());

        $ops = $service->getOperations();
        $this->assertCount(1, $ops);

        foreach ($ops as $operation) {
            $this->assertInstanceOf('Laminas\ApiTools\Documentation\Operation', $operation);
            $statusCodes = $operation->getResponseStatusCodes();
            switch ($operation->getHttpMethod()) {
                case 'GET':
                    $this->assertEquals(
                        $docConfig['Test\V1\Rpc\Ping\Controller']['GET']['description'],
                        $operation->getDescription()
                    );
                    $this->assertEquals(
                        $docConfig['Test\V1\Rpc\Ping\Controller']['GET']['request'],
                        $operation->getRequestDescription()
                    );
                    $this->assertEquals(
                        $docConfig['Test\V1\Rpc\Ping\Controller']['GET']['response'],
                        $operation->getResponseDescription()
                    );
                    $this->assertFalse($operation->requiresAuthorization());
                    $this->assertContainsStatusCodes(['406', '415', '200'], $statusCodes);
                    break;
                default:
                    $this->fail('Unexpected HTTP method encountered: ' . $operation->getHttpMethod());
                    break;
            }
        }
    }

    public function testGetFieldsForEntityMethods()
    {
        $api = $this->apiFactory->createApi('Test', 1);
        $service = $this->apiFactory->createService($api, 'EntityFields');
        $this->assertEquals('EntityFields', $service->getName());
        $this->assertCount(1, $service->getFields('PUT'));
    }
}
