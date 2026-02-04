<?php

/*
 * Copyright (c) 2025. Numeric Wave
 *
 * Affero General Public License (AGPL) v3
 *
 * For more information, please refer to the LICENSE file at the root of the project.
 */

namespace Lucca\Bundle\MinuteBundle\Manager;

use Lucca\Bundle\MinuteBundle\Entity\Control;
use Lucca\Bundle\MinuteBundle\Entity\Minute;

class ControlManager
{
    /**
     * Define Automatically accepted
     */
    public function defineAcceptedAutomatically(Control $control): Control
    {
        /** Automatically accepted  */
        if ($control->getStateControl() !== Control::STATE_INSIDE) {
            $control->setAccepted(Control::ACCEPTED_OK);
        }

        /** For this reasons - Control is automatically refused */
        if (in_array($control->getReason(), array(Control::REASON_REFUSED_LETTER, Control::REASON_UNCLAIMED_LETTER))) {
            $control->setAccepted(Control::ACCEPTED_NOK);
        }

        /** For this reasons - Control is automatically none completed */
        elseif (in_array($control->getReason(), array(Control::REASON_ERROR_ADRESS, Control::REASON_UNKNOW_ADRESS))) {
            $control->setAccepted(Control::ACCEPTED_NONE);
        }

        return $control;
    }

    /**
     * Count the number of controls excluding REFRESH controls that are not linked to any updating.
     *
     * @param Minute $minute
     * @return int
     */
    public function countValidControls(Minute $minute): int
    {
        return $minute->getControls()->count() - count($this->getControlsRefreshTypeLost($minute));
    }

    /**
     * Get controls without controls of type refresh that are not linked to an updating
     *
     * @param Minute $minute
     * @return array
     */
    public function getControlsRefreshTypeLost(Minute $minute): array
    {
        $updatingControlIds = array_flip(
            array_map(fn($control) => $control->getId(),
                array_merge(...array_map(fn($u) => $u->getControls()->toArray(), $minute->getUpdatings()->toArray()))
            )
        );

        return array_filter(
            $minute->getControls()->toArray(),
            fn($control) => $control->getType() === Control::TYPE_REFRESH && !isset($updatingControlIds[$control->getId()])
        );
    }
}
