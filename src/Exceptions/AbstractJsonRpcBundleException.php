<?php

namespace Ufo\JsonRpcBundle\Exceptions;

/**
 * ERROR CODES
 * ╔══════════════╦═══════════════════╦════════════════════════════════════════════════════╗
 * ║ Code         ║ Message           ║ Meaning                                            ║
 * ╠══════════════╬═══════════════════╬════════════════════════════════════════════════════╣
 * ║ -32700       ║ Parse error       ║ Invalid JSON was received by the server.           ║
 * ╠══════════════╬═══════════════════╬════════════════════════════════════════════════════╣
 * ║ -32600       ║ Invalid Request   ║ The JSON sent is not a valid Request object.       ║
 * ╠══════════════╬═══════════════════╬════════════════════════════════════════════════════╣
 * ║ -32601       ║ Method not found  ║ The method does not exist / is not available.      ║
 * ╠══════════════╬═══════════════════╬════════════════════════════════════════════════════╣
 * ║ -32602       ║ Invalid params    ║ Invalid method parameter(s).                       ║
 * ╠══════════════╬═══════════════════╬════════════════════════════════════════════════════╣
 * ║ -32603       ║ Internal error    ║ Internal JSON-RPC error.                           ║
 * ╠══════════════╬═══════════════════╬════════════════════════════════════════════════════╣
 * ║ -32500       ║ Application error ║ Runtime error on procedure.                        ║
 * ╠══════════════╬═══════════════════╬════════════════════════════════════════════════════╣
 * ║ -32400       ║ System error      ║ Logic error on application.                        ║
 * ╠══════════════╬═══════════════════╬════════════════════════════════════════════════════╣
 * ║ -32300       ║ Transport error   ║ Error transfer data.                               ║
 * ╠══════════════╬═══════════════════╬════════════════════════════════════════════════════╣
 * ║ from -32000  ║ Server error      ║ Reserved for implementation-defined server-errors. ║
 * ║ to   -32099  ║                   ║                                                    ║
 * ╚══════════════╩═══════════════════╩════════════════════════════════════════════════════╝
 */
abstract class AbstractJsonRpcBundleException extends \Exception
{
    const DEFAULT_CODE = -32603;
    protected $code = self::DEFAULT_CODE;

    public static function fromThrowable(\Throwable $e)
    {
        return new static(
            $e->getMessage(),
            $e->getCode(),
            $e
        );
    }
}
