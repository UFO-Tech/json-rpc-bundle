<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 12:19
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node\Interfaces;


interface ICanTransformToArray
{
    /**
     * @return array
     */
    public function toArray(): array;
}