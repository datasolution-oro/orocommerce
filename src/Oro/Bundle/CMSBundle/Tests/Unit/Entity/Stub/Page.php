<?php

namespace Oro\Bundle\CMSBundle\Tests\Unit\Entity\Stub;

use Oro\Bundle\CMSBundle\Entity\Page as BasePage;
use Oro\Bundle\LocaleBundle\Tests\Unit\Entity\Stub\LocalizedEntityTrait;

class Page extends BasePage
{
    use LocalizedEntityTrait;

    protected $organizationField;

    /**
     * @var array
     */
    protected $localizedFields = [
        'title' => 'titles',
        'slug' => 'slugs',
    ];

    /**
     * {@inheritdoc}
     */
    public function __call($name, $arguments)
    {
        return $this->localizedMethodCall($this->localizedFields, $name, $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function __get($name)
    {
        return $this->localizedFieldGet($this->localizedFields, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function __set($name, $value)
    {
        return $this->localizedFieldSet($this->localizedFields, $name, $value);
    }

    public function getOrganizationField()
    {
        return $this->organizationField;
    }

    public function setOrganizationField($organizationField): self
    {
        $this->organizationField = $organizationField;

        return $this;
    }
}
