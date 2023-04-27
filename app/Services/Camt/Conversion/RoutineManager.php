<?php
/*
 * RoutineManager.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Camt\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\Camt\Transaction;
use App\Services\Session\Constants;
use App\Services\Shared\Authentication\IsRunningCli;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\Storage\StorageService;
use Genkgo\Camt\Camt053\DTO\Statement as CamtStatement;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Message;
use Genkgo\Camt\Reader;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use IsRunningCli;
    use GeneratesIdentifier;

    private array         $allErrors;
    private array         $allMessages;
    private array         $allWarnings;
    private Configuration $configuration;
    private string        $content;
    private bool          $forceCli = false;

    private TransactionExtractor $transactionExtractor;
    private TransactionConverter $transactionConverter;
    /**
     *
     */
    public function __construct(?string $identifier)
    {
        $this->forceCli    = false; // used in POST auto import
        $this->content     = '';    // used in CLI
        $this->allErrors   = [];
        $this->allWarnings = [];
        $this->allMessages = [];
        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
    }

    /**
     * @return array
     */
    public function getAllErrors(): array
    {
        return $this->allErrors;
    }

    /**
     * @return array
     */
    public function getAllMessages(): array
    {
        return $this->allMessages;
    }

    /**
     * @return array
     */
    public function getAllWarnings(): array
    {
        return $this->allWarnings;
    }

    /**
     * @inheritDoc
     * @throws ImporterErrorException
     */
    public function setConfiguration(Configuration $configuration): void
    {
        // save config
        $this->configuration = $configuration;

        // make objects
        $this->transactionExtractor = new TransactionExtractor($this->configuration);
        $this->transactionConverter = new TransactionConverter($this->configuration);

        // share config
        //$this->columnValueConverter       = new ColumnValueConverter($this->configuration);
        //$this->pseudoTransactionProcessor = new PseudoTransactionProcessor($this->configuration->getDefaultAccount()); use from data
        // TODO find DefaultAccount

        // set identifier:
        //$this->csvFileProcessor->setIdentifier($this->identifier);
        //$this->lineProcessor->setIdentifier($this->identifier);
        //$this->columnValueConverter->setIdentifier($this->identifier);
        //$this->pseudoTransactionProcessor->setIdentifier($this->identifier);
    }

    /**
     * @inheritDoc
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));

        // get XML file
        $camtMessage = $this->getCamtMessage();
        if (null === $camtMessage) {
            die('CAMT is null');
        }
        // get raw messages
        $rawTransactions = $this->transactionExtractor->extractTransactions($camtMessage);

        // get intermediate result (still needs processing like mapping etc)
        $pseudoTransactions =$this->transactionConverter->convert($rawTransactions);


        // TODO -> hier muss alles ausgelesen werden

        // TODO -> CALL CAMT EXTRACTOR
        //try {
        //$this->csvFileProcessor->setReader(FileReader::getReaderFromSession($this->configuration->isConversion()));
        //} catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
        //    throw new ImporterErrorException($e->getMessage(), 0, $e);
        //}
        //}

        //$CSVLines = $this->csvFileProcessor->processCSVFile();

        // convert raw lines into arrays with individual ColumnValues
        //$valueArrays = $this->lineProcessor->processCSVLines($CSVLines);

        // convert value arrays into (pseudo) transactions.
        //$pseudo = $this->columnValueConverter->processValueArrays($valueArrays);

        // TODO call my extractor here -> create PSEUDO

        // convert pseudo transactions into actual transactions.
        $transactions = $this->pseudoTransactionProcessor->processPseudo($pseudo);

        $count = count($CSVLines);
        $this->mergeMessages($count);
        $this->mergeWarnings($count);
        $this->mergeErrors($count);

        file_put_contents('file_put_contents.txt', $transactions);

        return $transactions;
    }

    /**
     * @param int $count
     */
    private function mergeMessages(int $count): void
    {
        $one   = $this->csvFileProcessor->getMessages();
        $two   = $this->lineProcessor->getMessages();
        $three = $this->columnValueConverter->getMessages();
        $four  = $this->pseudoTransactionProcessor->getMessages();
        $total = [];
        for ($i = 0; $i < $count; $i++) {
            $total[$i] = array_merge(
                $one[$i] ?? [],
                $two[$i] ?? [],
                $three[$i] ?? [],
                $four[$i] ?? [],
            );
        }

        $this->allMessages = $total;
    }

    /**
     * @param int $count
     */
    private function mergeWarnings(int $count): void
    {
        $one   = $this->csvFileProcessor->getWarnings();
        $two   = $this->lineProcessor->getWarnings();
        $three = $this->columnValueConverter->getWarnings();
        $four  = $this->pseudoTransactionProcessor->getWarnings();
        $total = [];
        for ($i = 0; $i < $count; $i++) {
            $total[$i] = array_merge(
                $one[$i] ?? [],
                $two[$i] ?? [],
                $three[$i] ?? [],
                $four[$i] ?? [],
            );
        }
        $this->allWarnings = $total;
    }

    /**
     * @param int $count
     */
    private function mergeErrors(int $count): void
    {
        $one   = $this->csvFileProcessor->getErrors();
        $two   = $this->lineProcessor->getErrors();
        $three = $this->columnValueConverter->getErrors();
        $four  = $this->pseudoTransactionProcessor->getErrors();
        $total = [];
        for ($i = 0; $i < $count; $i++) {
            $total[$i] = array_merge(
                $one[$i] ?? [],
                $two[$i] ?? [],
                $three[$i] ?? [],
                $four[$i] ?? [],
            );
        }

        $this->allErrors = $total;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @param bool $forceCli
     */
    public function setForceCli(bool $forceCli): void
    {
        $this->forceCli = $forceCli;
    }

    /**
     * @return Message|null
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function getCamtMessage(): ?Message
    {
        $camtReader  = new Reader(Config::getDefault());
        $camtMessage = null;
        // check if CLI or not and read as appropriate:
        if ('' !== $this->content) {
            // seems the CLI part
            $camtMessage = $camtReader->readString($this->content); // -> Level A
        }
        if ('' === $this->content) {
            $camtMessage = $camtReader->readString(StorageService::getContent(session()->get(Constants::UPLOAD_DATA_FILE))); // -> Level A
        }

        return $camtMessage;
    }
}
