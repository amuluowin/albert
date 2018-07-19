<?php
declare(strict_types=1);

namespace yii\swoole\kafka\Producer;

use yii\swoole\kafka\Exception;
use yii\swoole\kafka\LoggerTrait;
use yii\swoole\kafka\Producer\RecordValidator;
use yii\swoole\kafka\ProducerConfig;
use yii\swoole\kafka\Protocol\Protocol;
use yii\swoole\kafka\Broker;
use function array_keys;
use function count;
use function explode;
use function json_encode;
use function shuffle;
use function sprintf;
use function substr;
use function trim;
use yii\swoole\kafka\CoroSocket;

class CoroProcess
{
    use LoggerTrait;
    /** @var RecordValidator */
    private $recordValidator;

    /**
     * @var int
     */
    private $retry = 0;
    /**
     * @var int
     */
    private $total = 10;

    public function __construct(?RecordValidator $recordValidator = null)
    {
        $this->recordValidator = $recordValidator ?? new RecordValidator();

        $config = $this->getConfig();
        \yii\swoole\kafka\Protocol::init($config->getBrokerVersion(), $this->logger);

        $broker = $this->getBroker();
        $broker->setConfig($config);

        $this->syncMeta();
    }

    /**
     * @param mixed[][] $recordSet
     *
     * @return mixed[]
     *
     * @throws \yii\swoole\kafka\Exception
     */
    public function send(array $recordSet): array
    {
        $broker = $this->getBroker();
        $config = $this->getConfig();

        $requiredAck = $config->getRequiredAck();
        $timeout = $config->getTimeout();
        $compression = $config->getCompression();

        // get send message
        // data struct
        //  topic:
        //  partId:
        //  key:
        //  value:
        if (empty($recordSet)) {
            return [];
        }

        $sendData = $this->convertRecordSet($recordSet);
        $result = [];
        foreach ($sendData as $brokerId => $topicList) {
            $connect = $broker->getDataConnect((string)$brokerId, true);

            if ($connect === null) {
                return [];
            }

            $params = [
                'required_ack' => $requiredAck,
                'timeout' => $timeout,
                'data' => $topicList,
                'compression' => $compression,
            ];

            $this->debug('Send message start, params:' . json_encode($params));
            $requestData = \yii\swoole\kafka\Protocol::encode(\yii\swoole\kafka\Protocol::PRODUCE_REQUEST, $params);
            $connect->write($requestData);

            if ($requiredAck !== 0) { // If it is 0 the server will not send any response
                $data = $connect->read(0);
                $dataLen = substr($data, 0, 4);
                $dataLen = Protocol::unpack(Protocol::BIT_B32, $dataLen);
                $correlationId = Protocol::unpack(Protocol::BIT_B32, substr($data, 4, 4));
                $ret = \yii\swoole\kafka\Protocol::decode(\yii\swoole\kafka\Protocol::PRODUCE_REQUEST, substr($data, 8));

                $result[] = $ret;
            }
        }

        return $result;
    }

    private function reSyncMeta(CoroSocket $socket, string $requestData): array
    {
        $socket->write($requestData);
        $data = $socket->read(60);
        $dataLen = substr($data, 0, 4);
        $dataLen = Protocol::unpack(Protocol::BIT_B32, $dataLen);
        $correlationId = Protocol::unpack(Protocol::BIT_B32, substr($data, 4, 4));
        $result = \yii\swoole\kafka\Protocol::decode(\yii\swoole\kafka\Protocol::METADATA_REQUEST, substr($data, 8));

        if (!isset($result['brokers'], $result['topics'])) {
            throw new Exception('Get metadata is fail, brokers or topics is null.');
        }
        if (count($result['topics']) === 0) {
            $this->retry++;
            if ($this->retry === $this->total) {
                throw new Exception('Get metadata is fail, brokers or topics is null.');
            } else {
                \Co::sleep(2);
                $result = $this->reSyncMeta($socket, $requestData);
            }
        }
        return $result;
    }

    public function syncMeta(): void
    {
        $this->debug('Start sync metadata request');

        $brokerList = ProducerConfig::getInstance()->getMetadataBrokerList();
        $brokerHost = [];

        foreach (explode(',', $brokerList) as $key => $val) {
            if (trim($val)) {
                $brokerHost[] = $val;
            }
        }

        if (count($brokerHost) === 0) {
            throw new Exception('No valid broker configured');
        }

        shuffle($brokerHost);
        $broker = $this->getBroker();

        foreach ($brokerHost as $host) {
            $socket = $broker->getMetaConnect($host, true);

            if ($socket === null) {
                continue;
            }

            $params = [];
            $this->debug('Start sync metadata request params:' . json_encode($params));
            $requestData = \yii\swoole\kafka\Protocol::encode(\yii\swoole\kafka\Protocol::METADATA_REQUEST, $params);
            $result = $this->reSyncMeta($socket, $requestData);
            $broker = $this->getBroker();
            $broker->setData($result['topics'], $result['brokers']);

            return;
        }

        throw new Exception(
            sprintf(
                'It was not possible to establish a connection for metadata with the brokers "%s"',
                $brokerList
            )
        );
    }

    /**
     * @param string[][] $recordSet
     *
     * @return mixed[]
     */
    protected function convertRecordSet(array $recordSet): array
    {
        $sendData = [];
        $broker = $this->getBroker();
        $topics = $broker->getTopics();

        foreach ($recordSet as $record) {
            $this->recordValidator->validate($record, $topics);

            $topicMeta = $topics[$record['topic']];
            $partNums = array_keys($topicMeta);
            shuffle($partNums);

            $partId = isset($record['partId'], $topicMeta[$record['partId']]) ? $record['partId'] : $partNums[0];

            $brokerId = $topicMeta[$partId];
            $topicData = [];
            if (isset($sendData[$brokerId][$record['topic']])) {
                $topicData = $sendData[$brokerId][$record['topic']];
            }

            $partition = [];
            if (isset($topicData['partitions'][$partId])) {
                $partition = $topicData['partitions'][$partId];
            }

            $partition['partition_id'] = $partId;

            if (trim($record['key'] ?? '') !== '') {
                $partition['messages'][] = ['value' => $record['value'], 'key' => $record['key']];
            } else {
                $partition['messages'][] = $record['value'];
            }

            $topicData['partitions'][$partId] = $partition;
            $topicData['topic_name'] = $record['topic'];
            $sendData[$brokerId][$record['topic']] = $topicData;
        }

        return $sendData;
    }

    private function getBroker(): Broker
    {
        return Broker::getInstance();
    }

    private function getConfig(): ProducerConfig
    {
        return ProducerConfig::getInstance();
    }
}
