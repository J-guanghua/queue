<?php
namespace guanghua\queue\base;

use guanghua\queue\base\Basics;

/**
 * 类序列化器
 */
interface Serializer
{
    /**
     * @param Job|mixed $job
     * @return string
     */
    public function serialize($job);

    /**
     * @param string $serialized
     * @return Job
     */
    public function unserialize($serialized);
}

/**
 * Class PhpSerializer
 */
class PhpSerializer extends Basics implements Serializer
{
    /**
     * @inheritdoc
     */
    public function serialize($job)
    {
        return serialize($job);
    }

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        return unserialize($serialized);
    }
}
