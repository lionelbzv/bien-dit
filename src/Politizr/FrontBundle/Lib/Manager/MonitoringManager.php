<?php
namespace Politizr\FrontBundle\Lib\Manager;

use Politizr\Exception\InconsistentDataException;
use Politizr\Exception\FormValidationException;

use Politizr\Model\PMAppException;

/**
 * DB manager service for monitoring.
 *
 * @author Lionel Bouzonville
 */
class MonitoringManager
{
    private $logger;

    /**
     *
     * @param @logger
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    /* ######################################################################################################## */
    /*                                                  PRIVATE                                                 */
    /* ######################################################################################################## */

    /**
     * Concatene all exceptions stack trace
     *
     * @param Exception $exception
     * @return string
     */
    private function concatePreviousStackTrace(\Exception $exception)
    {
        if (null === $exception->getPrevious()) {
            return $exception->getTraceAsString();
        }
        
        $stackTrace = $this->concatePreviousStackTrace($exception->getPrevious());

        return $stackTrace . " ##############\n " . $exception->getTraceAsString();
    }

    /* ######################################################################################################## */
    /*                                            CRUD OPERATIONS                                               */
    /* ######################################################################################################## */

    /**
     *
     * @param \Exception $exception
     * @param int $userId
     * @return PMAppException
     */
    public function createAppException($exception, $userId = null)
    {
        $pmAppException = new PMAppException();

        $pmAppException->setPUserId($userId);

        $pmAppException->setFile($exception->getFile());
        $pmAppException->setLine($exception->getLine());

        $pmAppException->setCode($exception->getCode());
        $pmAppException->setMessage($exception->getMessage());

        $stackTrace = $this->concatePreviousStackTrace($exception);
        $pmAppException->setStackTrace($stackTrace);

        $pmAppException->save();

        return $pmAppException;
    }
}