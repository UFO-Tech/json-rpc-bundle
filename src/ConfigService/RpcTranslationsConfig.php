<?php

namespace Ufo\JsonRpcBundle\ConfigService;

/**
 * Extra directories with RPC method translations, read by the compiler pass.
 * Each directory has the same layout as the application's `translations/`:
 * `rpc.<locale>.yaml` (base) and `rpc/<group>.<locale>.yaml` (overrides for an API group).
 */
final class RpcTranslationsConfig
{
    const string NAME = 'translations';
    const string PATHS = 'paths';
    const string PARAMETER = RpcMainConfig::NAME.'.'.self::NAME.'.'.self::PATHS;
}
