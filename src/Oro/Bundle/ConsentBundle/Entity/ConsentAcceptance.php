<?php

namespace Oro\Bundle\ConsentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Extend\Entity\Autocomplete\OroConsentBundle_Entity_ConsentAcceptance;
use Oro\Bundle\CMSBundle\Entity\Page;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\EntityBundle\EntityProperty\CreatedAtAwareInterface;
use Oro\Bundle\EntityBundle\EntityProperty\CreatedAtAwareTrait;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityInterface;
use Oro\Bundle\EntityExtendBundle\Entity\ExtendEntityTrait;

/**
 * Entity represents accepted consents with certain landing page by CustomerUser
 * CustomerUser relation is added via migration
 *
 * @ORM\Entity(repositoryClass="Oro\Bundle\ConsentBundle\Entity\Repository\ConsentAcceptanceRepository")
 *
 * @ORM\Table(
 *     name="oro_consent_acceptance",
 *     uniqueConstraints={
 *          @ORM\UniqueConstraint(
 *              name="oro_customeru_consent_uidx",
 *              columns={"consent_id","customerUser_id"}
 *          )
 *      }
 * )
 * @Config()
 *
 * @method CustomerUser getCustomerUser()
 * @method setCustomerUser(CustomerUser $customerUser)
 * @mixin OroConsentBundle_Entity_ConsentAcceptance
 */
class ConsentAcceptance implements
    CreatedAtAwareInterface,
    ExtendEntityInterface
{
    use CreatedAtAwareTrait;
    use ExtendEntityTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var Page
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\CMSBundle\Entity\Page")
     * @ORM\JoinColumn(name="landing_page_id", referencedColumnName="id", nullable=true, onDelete="RESTRICT")
     */
    protected $landingPage;

    /**
     * @var Consent
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\ConsentBundle\Entity\Consent", inversedBy="acceptances")
     * @ORM\JoinColumn(name="consent_id", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     */
    protected $consent;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Page|null
     */
    public function getLandingPage()
    {
        return $this->landingPage;
    }

    /**
     * @param Page $landingPage
     *
     * @return $this
     */
    public function setLandingPage(Page $landingPage)
    {
        $this->landingPage = $landingPage;

        return $this;
    }

    /**
     * @return Consent
     */
    public function getConsent()
    {
        return $this->consent;
    }

    /**
     * @param Consent $consent
     *
     * @return $this
     */
    public function setConsent(Consent $consent)
    {
        $this->consent = $consent;

        return $this;
    }
}
