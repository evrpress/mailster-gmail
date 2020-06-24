<?php

declare (strict_types=1);
/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Mailster\Monolog\Handler;

use Mailster\Monolog\Formatter\LineFormatter;
use Mailster\Monolog\Formatter\FormatterInterface;
use Mailster\Monolog\Logger;
use Mailster\Monolog\Utils;
use Mailster\PhpConsole\Connector;
use Mailster\PhpConsole\Handler as VendorPhpConsoleHandler;
use Mailster\PhpConsole\Helper;
/**
 * Monolog handler for Google Chrome extension "PHP Console"
 *
 * Display PHP error/debug log messages in Google Chrome console and notification popups, executes PHP code remotely
 *
 * Usage:
 * 1. Install Google Chrome extension https://chrome.google.com/webstore/detail/php-console/nfhmhhlpfleoednkpnnnkolmclajemef
 * 2. See overview https://github.com/barbushin/php-console#overview
 * 3. Install PHP Console library https://github.com/barbushin/php-console#installation
 * 4. Example (result will looks like http://i.hizliresim.com/vg3Pz4.png)
 *
 *      $logger = new \Monolog\Logger('all', array(new \Monolog\Handler\PHPConsoleHandler()));
 *      \Monolog\ErrorHandler::register($logger);
 *      echo $undefinedVar;
 *      $logger->debug('SELECT * FROM users', array('db', 'time' => 0.012));
 *      PC::debug($_SERVER); // PHP Console debugger for any type of vars
 *
 * @author Sergey Barbushin https://www.linkedin.com/in/barbushin
 */
class PHPConsoleHandler extends \Mailster\Monolog\Handler\AbstractProcessingHandler
{
    private $options = [
        'enabled' => \true,
        // bool Is PHP Console server enabled
        'classesPartialsTraceIgnore' => ['Monolog\\'],
        // array Hide calls of classes started with...
        'debugTagsKeysInContext' => [0, 'tag'],
        // bool Is PHP Console server enabled
        'useOwnErrorsHandler' => \false,
        // bool Enable errors handling
        'useOwnExceptionsHandler' => \false,
        // bool Enable exceptions handling
        'sourcesBasePath' => null,
        // string Base path of all project sources to strip in errors source paths
        'registerHelper' => \true,
        // bool Register PhpConsole\Helper that allows short debug calls like PC::debug($var, 'ta.g.s')
        'serverEncoding' => null,
        // string|null Server internal encoding
        'headersLimit' => null,
        // int|null Set headers size limit for your web-server
        'password' => null,
        // string|null Protect PHP Console connection by password
        'enableSslOnlyMode' => \false,
        // bool Force connection by SSL for clients with PHP Console installed
        'ipMasks' => [],
        // array Set IP masks of clients that will be allowed to connect to PHP Console: array('192.168.*.*', '127.0.0.1')
        'enableEvalListener' => \false,
        // bool Enable eval request to be handled by eval dispatcher(if enabled, 'password' option is also required)
        'dumperDetectCallbacks' => \false,
        // bool Convert callback items in dumper vars to (callback SomeClass::someMethod) strings
        'dumperLevelLimit' => 5,
        // int Maximum dumped vars array or object nested dump level
        'dumperItemsCountLimit' => 100,
        // int Maximum dumped var same level array items or object properties number
        'dumperItemSizeLimit' => 5000,
        // int Maximum length of any string or dumped array item
        'dumperDumpSizeLimit' => 500000,
        // int Maximum approximate size of dumped vars result formatted in JSON
        'detectDumpTraceAndSource' => \false,
        // bool Autodetect and append trace data to debug
        'dataStorage' => null,
    ];
    /** @var Connector */
    private $connector;
    /**
     * @param  array             $options   See \Monolog\Handler\PHPConsoleHandler::$options for more details
     * @param  Connector|null    $connector Instance of \PhpConsole\Connector class (optional)
     * @param  string|int        $level     The minimum logging level at which this handler will be triggered.
     * @param  bool              $bubble    Whether the messages that are handled can bubble up the stack or not.
     * @throws \RuntimeException
     */
    public function __construct(array $options = [], ?\Mailster\PhpConsole\Connector $connector = null, $level = \Mailster\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        if (!\class_exists('Mailster\\PhpConsole\\Connector')) {
            throw new \RuntimeException('PHP Console library not found. See https://github.com/barbushin/php-console#installation');
        }
        parent::__construct($level, $bubble);
        $this->options = $this->initOptions($options);
        $this->connector = $this->initConnector($connector);
    }
    private function initOptions(array $options) : array
    {
        $wrongOptions = \array_diff(\array_keys($options), \array_keys($this->options));
        if ($wrongOptions) {
            throw new \RuntimeException('Unknown options: ' . \implode(', ', $wrongOptions));
        }
        return \array_replace($this->options, $options);
    }
    /**
     * @suppress PhanTypeMismatchArgument
     */
    private function initConnector(?\Mailster\PhpConsole\Connector $connector = null) : \Mailster\PhpConsole\Connector
    {
        if (!$connector) {
            if ($this->options['dataStorage']) {
                \Mailster\PhpConsole\Connector::setPostponeStorage($this->options['dataStorage']);
            }
            $connector = \Mailster\PhpConsole\Connector::getInstance();
        }
        if ($this->options['registerHelper'] && !\Mailster\PhpConsole\Helper::isRegistered()) {
            \Mailster\PhpConsole\Helper::register();
        }
        if ($this->options['enabled'] && $connector->isActiveClient()) {
            if ($this->options['useOwnErrorsHandler'] || $this->options['useOwnExceptionsHandler']) {
                $handler = \Mailster\PhpConsole\Handler::getInstance();
                $handler->setHandleErrors($this->options['useOwnErrorsHandler']);
                $handler->setHandleExceptions($this->options['useOwnExceptionsHandler']);
                $handler->start();
            }
            if ($this->options['sourcesBasePath']) {
                $connector->setSourcesBasePath($this->options['sourcesBasePath']);
            }
            if ($this->options['serverEncoding']) {
                $connector->setServerEncoding($this->options['serverEncoding']);
            }
            if ($this->options['password']) {
                $connector->setPassword($this->options['password']);
            }
            if ($this->options['enableSslOnlyMode']) {
                $connector->enableSslOnlyMode();
            }
            if ($this->options['ipMasks']) {
                $connector->setAllowedIpMasks($this->options['ipMasks']);
            }
            if ($this->options['headersLimit']) {
                $connector->setHeadersLimit($this->options['headersLimit']);
            }
            if ($this->options['detectDumpTraceAndSource']) {
                $connector->getDebugDispatcher()->detectTraceAndSource = \true;
            }
            $dumper = $connector->getDumper();
            $dumper->levelLimit = $this->options['dumperLevelLimit'];
            $dumper->itemsCountLimit = $this->options['dumperItemsCountLimit'];
            $dumper->itemSizeLimit = $this->options['dumperItemSizeLimit'];
            $dumper->dumpSizeLimit = $this->options['dumperDumpSizeLimit'];
            $dumper->detectCallbacks = $this->options['dumperDetectCallbacks'];
            if ($this->options['enableEvalListener']) {
                $connector->startEvalRequestsListener();
            }
        }
        return $connector;
    }
    public function getConnector() : \Mailster\PhpConsole\Connector
    {
        return $this->connector;
    }
    public function getOptions() : array
    {
        return $this->options;
    }
    public function handle(array $record) : bool
    {
        if ($this->options['enabled'] && $this->connector->isActiveClient()) {
            return parent::handle($record);
        }
        return !$this->bubble;
    }
    /**
     * Writes the record down to the log of the implementing handler
     */
    protected function write(array $record) : void
    {
        if ($record['level'] < \Mailster\Monolog\Logger::NOTICE) {
            $this->handleDebugRecord($record);
        } elseif (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            $this->handleExceptionRecord($record);
        } else {
            $this->handleErrorRecord($record);
        }
    }
    private function handleDebugRecord(array $record) : void
    {
        $tags = $this->getRecordTags($record);
        $message = $record['message'];
        if ($record['context']) {
            $message .= ' ' . \Mailster\Monolog\Utils::jsonEncode($this->connector->getDumper()->dump(\array_filter($record['context'])), null, \true);
        }
        $this->connector->getDebugDispatcher()->dispatchDebug($message, $tags, $this->options['classesPartialsTraceIgnore']);
    }
    private function handleExceptionRecord(array $record) : void
    {
        $this->connector->getErrorsDispatcher()->dispatchException($record['context']['exception']);
    }
    private function handleErrorRecord(array $record) : void
    {
        $context = $record['context'];
        $this->connector->getErrorsDispatcher()->dispatchError($context['code'] ?? null, $context['message'] ?? $record['message'], $context['file'] ?? null, $context['line'] ?? null, $this->options['classesPartialsTraceIgnore']);
    }
    private function getRecordTags(array &$record)
    {
        $tags = null;
        if (!empty($record['context'])) {
            $context =& $record['context'];
            foreach ($this->options['debugTagsKeysInContext'] as $key) {
                if (!empty($context[$key])) {
                    $tags = $context[$key];
                    if ($key === 0) {
                        \array_shift($context);
                    } else {
                        unset($context[$key]);
                    }
                    break;
                }
            }
        }
        return $tags ?: \strtolower($record['level_name']);
    }
    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter() : \Mailster\Monolog\Formatter\FormatterInterface
    {
        return new \Mailster\Monolog\Formatter\LineFormatter('%message%');
    }
}
