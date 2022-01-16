<?php

use App\Microservices\Receiver\MessagingConfiguration;
use App\Microservices\Receiver\OrderServiceReceiver;
use Ecotone\Modelling\DistributedBus;
use Ecotone\Modelling\QueryBus;
use Enqueue\AmqpExt\AmqpConnectionFactory;
use PHPUnit\Framework\Assert;

require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/../ecotone-lite.php";
// Receiver
$receiver = createMessaging([Enqueue\AmqpExt\AmqpConnectionFactory::class => new AmqpConnectionFactory("amqp://guest:guest@rabbitmq:5672/%2f")], "App\Microservices\Receiver", "Microservices", MessagingConfiguration::SERVICE_NAME);
$receiver->run(MessagingConfiguration::SERVICE_NAME);
/** @var QueryBus $queryBus */
$queryBus = $receiver->getGatewayByName(QueryBus::class);

// Publisher
$publisher = createMessaging([Enqueue\AmqpExt\AmqpConnectionFactory::class => new AmqpConnectionFactory("amqp://guest:guest@rabbitmq:5672/%2f")], "App\Microservices\Publisher", "Microservices", \App\Microservices\Publisher\MessagingConfiguration::SERVICE_NAME);
/** @var DistributedBus $distributedBus */
$distributedBus = $publisher->getGatewayByName(DistributedBus::class);

echo "Sending command to Order Service, to order milk and bread\n";
$distributedBus->sendCommand(
    MessagingConfiguration::SERVICE_NAME,
    OrderServiceReceiver::COMMAND_HANDLER_ROUTING,
    '{"personId":123,"products":["milk","bread"]}',
    "application/json"
);
echo "Before running consumer and handling command, there should be no ordered products\n";
Assert::assertEquals([], $queryBus->sendWithRouting(OrderServiceReceiver::GET_ALL_ORDERED_PRODUCTS));

echo "After running consumer, there should be milk and bread ordered\n";
$receiver->run(MessagingConfiguration::SERVICE_NAME);
Assert::assertEquals([123 => ["milk", "bread"]], $queryBus->sendWithRouting(OrderServiceReceiver::GET_ALL_ORDERED_PRODUCTS));


echo "Sending event that user was banned\n";
$distributedBus->publishEvent(
    "user.was_banned",
    '{"personId":123}',
    "application/json"
);

echo "Before running consumer and handling events, there should milk and bread ordered\n";
Assert::assertEquals([123 => ["milk", "bread"]], $queryBus->sendWithRouting(OrderServiceReceiver::GET_ALL_ORDERED_PRODUCTS));

echo "After running consumer, there should be no orders\n";
$receiver->run(MessagingConfiguration::SERVICE_NAME);
Assert::assertEquals([], $queryBus->sendWithRouting(OrderServiceReceiver::GET_ALL_ORDERED_PRODUCTS));