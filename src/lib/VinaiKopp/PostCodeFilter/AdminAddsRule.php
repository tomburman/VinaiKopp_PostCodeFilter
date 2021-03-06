<?php

namespace VinaiKopp\PostCodeFilter;

use VinaiKopp\PostCodeFilter\Exceptions\RuleForGroupAndCountryAlreadyExistsException;
use VinaiKopp\PostCodeFilter\Rule\ExistingRule;
use VinaiKopp\PostCodeFilter\Storage\ReadModel\RuleReader;
use VinaiKopp\PostCodeFilter\Storage\ReadModel\RuleSpecByCountryAndGroupIds;
use VinaiKopp\PostCodeFilter\Storage\WriteModel\RuleToAdd;
use VinaiKopp\PostCodeFilter\Storage\WriteModel\RuleWriter;

class AdminAddsRule
{
    /**
     * @var RuleWriter
     */
    private $ruleWriter;

    /**
     * @var RuleReader
     */
    private $ruleReader;

    public function __construct(RuleWriter $ruleWriter, RuleReader $ruleReader)
    {
        $this->ruleWriter = $ruleWriter;
        $this->ruleReader = $ruleReader;
    }

    /**
     * @param RuleToAdd $ruleToAdd
     * @throws \Exception
     */
    public function addRule(RuleToAdd $ruleToAdd)
    {
        try {
            $this->ruleWriter->beginTransaction();
            $this->validateNoConflictingRuleExists($ruleToAdd);
            $this->ruleWriter->createRule($ruleToAdd);
            $this->ruleWriter->commitTransaction();
        } catch (\Exception $e) {
            $this->ruleWriter->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * @param int[] $customerGroupIds
     * @param string $iso2Country
     * @param string[] $postCodes
     */
    public function addRuleFromScalars(array $customerGroupIds, $iso2Country, array $postCodes)
    {
        $this->addRule(RuleToAdd::createFromScalars($customerGroupIds, $iso2Country, $postCodes));
    }

    /**
     * @param RuleToAdd $ruleToAdd
     * @throws RuleForGroupAndCountryAlreadyExistsException
     */
    private function validateNoConflictingRuleExists(RuleToAdd $ruleToAdd)
    {
        $ruleSpec = $this->createRuleSpec($ruleToAdd);
        $result = $this->ruleReader->findByCountryAndGroupIds($ruleSpec);
        if ($result instanceof ExistingRule) {
            throw $this->createRuleExistsException($result, $ruleToAdd);
        }
    }

    /**
     * @param RuleToAdd $ruleToAdd
     * @return RuleSpecByCountryAndGroupIds
     */
    private function createRuleSpec(RuleToAdd $ruleToAdd)
    {
        return new RuleSpecByCountryAndGroupIds($ruleToAdd->getCountry(), $ruleToAdd->getCustomerGroupIds());
    }

    /**
     * @param ExistingRule $existingRule
     * @param RuleToAdd $ruleToAdd
     * @return RuleForGroupAndCountryAlreadyExistsException
     */
    private function createRuleExistsException(ExistingRule $existingRule, RuleToAdd $ruleToAdd)
    {
        return new RuleForGroupAndCountryAlreadyExistsException(sprintf(
            'A rule for customer group(s) "%s" and country "%s" already exists',
            implode(', ', $existingRule->getCustomerGroupIdValues()),
            $ruleToAdd->getCountryValue()
        ));
    }
}
