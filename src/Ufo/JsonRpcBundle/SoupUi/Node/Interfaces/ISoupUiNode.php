<?php
/**
 * @author Doctor <doctor.netpeak@gmail.com>
 *
 *
 * Date: 08.06.2017
 * Time: 9:41
 */

namespace Ufo\JsonRpcBundle\SoupUi\Node\Interfaces;


interface ISoupUiNode extends ICanTransformToArray
{
    const SOUPUI_VERSION = '5.3.0';
    const SOUPUI_NS_URL = 'http://eviware.com/soapui/config';
    const SOUPUI_NS = 'con:';
    const SOUPUI_ROOT = 'soapui-project';

    /**
     * @return string
     */
    public function getTag();

    /**
     * @return array
     */
    public function getAttributes();

}