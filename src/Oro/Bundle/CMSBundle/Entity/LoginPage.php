<?php

namespace Oro\Bundle\CMSBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Extend\Entity\Autocomplete\OroCMSBundle_Entity_LoginPage;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\ConfigField;
use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityInterface;
use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityTrait;

/**
 * Login page entity class.
 *
 * @ORM\Table(name="oro_cms_login_page")
 * @ORM\Entity()
 * @Config(
 *      routeName="oro_cms_loginpage_index",
 *      routeUpdate="oro_cms_loginpage_update",
 *      defaultValues={
 *          "entity"={
 *              "icon"="fa-sign-in"
 *          },
 *          "security"={
 *              "type"="ACL",
 *              "group_name"=""
 *          },
 *          "dataaudit"={
 *              "auditable"=true
 *          }
 *      }
 * )
 * @method File getLogoImage()
 * @method LoginPage setLogoImage(File $image)
 * @method File getBackgroundImage()
 * @method LoginPage setBackgroundImage(File $image)
 * @mixin OroCMSBundle_Entity_LoginPage
 */
class LoginPage implements ExtendEntityInterface
{
    use ExtendEntityTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="top_content", type="text", nullable=true)
     * @ConfigField(
     *      defaultValues={
     *          "dataaudit"={
     *              "auditable"=true
     *          }
     *      }
     * )
     */
    protected $topContent;

    /**
     * @var string
     *
     * @ORM\Column(name="bottom_content", type="text", nullable=true)
     * @ConfigField(
     *      defaultValues={
     *          "dataaudit"={
     *              "auditable"=true
     *          }
     *      }
     * )
     */
    protected $bottomContent;

    /**
     * @var string
     *
     * @ORM\Column(name="css", type="text", nullable=true)
     * @ConfigField(
     *      defaultValues={
     *          "dataaudit"={
     *              "auditable"=true
     *          }
     *      }
     * )
     */
    protected $css;

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTopContent()
    {
        return $this->topContent;
    }

    /**
     * @param string|null $topContent
     * @return $this
     */
    public function setTopContent($topContent = null)
    {
        $this->topContent = $topContent;

        return $this;
    }

    /**
     * @return string
     */
    public function getBottomContent()
    {
        return $this->bottomContent;
    }

    /**
     * @param string|null $bottomContent
     * @return $this
     */
    public function setBottomContent($bottomContent = null)
    {
        $this->bottomContent = $bottomContent;

        return $this;
    }

    /**
     * @return string
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * @param string|null $css
     * @return $this
     */
    public function setCss($css = null)
    {
        $this->css = $css;

        return $this;
    }
}
