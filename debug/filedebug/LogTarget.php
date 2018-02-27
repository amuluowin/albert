<?php

namespace yii\swoole\debug\filedebug;

use yii\base\InvalidConfigException;
use yii\swoole\helpers\FileHelper;
use yii\swoole\helpers\SerializeHelper;

/**
 * 调试模块的日志记录器
 *
 * @package yii\swoole\debug\filedebug
 */
class LogTarget extends \yii\debug\LogTarget
{

    /**
     * @inheritdoc
     */
    public function export()
    {
        $path = $this->module->dataPath;
        FileHelper::createDirectory($path, $this->module->dirMode);

        $summary = $this->collectSummary();
        $dataFile = "$path/{$this->tag}.data";
        $data = [];
        $exceptions = [];
        foreach ($this->module->panels as $id => $panel) {
            try {
                $data[$id] = SerializeHelper::serialize($panel->save());
            } catch (\Exception $exception) {
                $exceptions[$id] = new FlattenException($exception);
            }
        }
        $data['summary'] = $summary;
        $data['exceptions'] = $exceptions;

        $fp = @fopen($dataFile, "a+");
        \Swoole\Coroutine::fwrite($fp, SerializeHelper::serialize($data));
        @fclose($fp);
        if ($this->module->fileMode !== null) {
            @chmod($dataFile, $this->module->fileMode);
        }

        $indexFile = "$path/index.data";
        $this->updateIndexFile($indexFile, $summary);
    }

    private function updateIndexFile($indexFile, $summary)
    {
        touch($indexFile);
        if (($fp = @fopen($indexFile, 'r+')) === false) {
            throw new InvalidConfigException("Unable to open debug data index file: $indexFile");
        }
        $manifest = \Swoole\Coroutine::fread($fp);

        if (empty($manifest)) {
            // error while reading index data, ignore and create new
            $manifest = [];
        } else {
            $manifest = SerializeHelper::unserialize($manifest);
        }

        $manifest[$this->tag] = $summary;
        $this->gc($manifest);

        ftruncate($fp, 0);
        rewind($fp);
        \Swoole\Coroutine::fwrite($fp, SerializeHelper::serialize($manifest));
        @fclose($fp);
        if ($this->module->fileMode !== null) {
            @chmod($indexFile, $this->module->fileMode);
        }
    }
}
