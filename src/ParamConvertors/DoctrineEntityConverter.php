<?php

namespace Ufo\JsonRpcBundle\ParamConvertors;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Throwable;
use Ufo\DTO\Helpers\TypeHintResolver;
use Ufo\RpcObject\RPC\Param;

use function array_values;
use function current;

#[AsTaggedItem(IParamConvertor::TAG, priority: 100)]
readonly class DoctrineEntityConverter implements IParamConvertor
{
    public function __construct(
        protected ?ManagerRegistry $registry = null
    ) {}

    public function toObject(float|int|string|null $value, array $context = [], ?callable $callback = null): ?object
    {
        $this->checkDoctrineExist();

        $class = $context[TypeHintResolver::CLASS_FQCN] ?? throw new LogicException("DoctrineEntityConverter: 'classFQCN' is required in context.");

        $em = $this->getEntityManager($class);
        $repo = $em->getRepository($class);
        $obj = $repo->find($value);

        if ($callback && $obj) {
            return $callback($value, $obj);
        }
        return $obj;
    }

    public function toScalar(object $object, array $context = [], ?callable $callback = null): string|int
    {
        $this->checkDoctrineExist();

        $scalar = match (true) {
            ($object instanceof Stringable) => $this->stringable($object),
            default => $this->byDoctrine($object),
        };

        return $callback ? $callback($scalar, $object) : $scalar;

    }

    protected function byDoctrine(object $object): string
    {
        $em = $this->getEntityManager($object::class);
        $id = $em->getClassMetadata($object::class)->getIdentifierValues($object);

        return current(array_values($id));
    }

    protected function stringable(Stringable $obj): string
    {
        return (string)$obj;
    }

    public function supported(string $classFQCN): bool
    {
        try {
            $this->checkDoctrineExist();
            $this->getEntityManager($classFQCN);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function checkDoctrineExist(): void
    {
        if ($this->registry === null) throw new LogicException("DoctrineEntityConverter cannot be used because Doctrine is not installed.");
    }

    protected function getEntityManager(string $class): ObjectManager
    {
        return $this->registry->getManagerForClass($class) ?? throw new LogicException("DoctrineEntityConverter: class $class is not a Doctrine entity.");
    }

    public function getParamAttr(string $classFQCN): Param
    {
        $em = $this->getEntityManager($classFQCN);
        $meta = $em->getClassMetadata($classFQCN);
        $ids = $meta->getIdentifierFieldNames();
        $type = $meta->getTypeOfField($ids[0] ?? 'id');

        return new Param(Param::bitFromType($type), context: [Param::C_CONVERTOR => $this::class]);
    }

}
