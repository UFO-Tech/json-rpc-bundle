<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 05.06.2017
 * Time: 12:53
 */

namespace Ufo\JsonRpcBundle\Server;


use Psr\Log\LoggerInterface;
use Laminas\Json\Server\Error;
use Laminas\Json\Server\Server;

class UfoZendServer extends Server
{

    public function __construct(protected ?LoggerInterface $logger = null)
    {
        parent::__construct();
    }

    /**
     * Indicate fault response
     *
     * @param  string $fault
     * @param  int $code
     * @param  mixed $data
     * @return Error
     */
    public function fault($fault = null, $code = 404, $data = null): Error
    {
        $error = parent::fault($fault, $code, $data);
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->error(
                (string)$error->getData(),
                [
                    'method' => $this->getRequest()->getMethod(),
                    'params' => $this->getRequest()->getParams()
                ]
            );
        }
        return $error;
    }

}