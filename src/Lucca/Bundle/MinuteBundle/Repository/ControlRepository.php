<?php

/*
 * Copyright (c) 2025. Numeric Wave
 *
 * Affero General Public License (AGPL) v3
 *
 * For more information, please refer to the LICENSE file at the root of the project.
 */

namespace Lucca\Bundle\MinuteBundle\Repository;

use Doctrine\ORM\{EntityRepository, NonUniqueResultException, QueryBuilder};

use Lucca\Bundle\AdherentBundle\Entity\Adherent;
use Lucca\Bundle\MinuteBundle\Entity\{Control, Minute};

class ControlRepository extends EntityRepository
{
    /*******************************************************************************************/
    /********************* Stats methods *****/
    /*******************************************************************************************/

    /**
     * Count type of decision between date of minute opening
     */
    public function findBetweenDates($minutes = null, $p_state = [Control::STATE_INSIDE, Control::STATE_INSIDE_WITHOUT_CONVOCATION]): mixed
    {
        $qb = $this->queryControlSimple();

        /************** Filters on minute to get ******************/
        if ($minutes && count($minutes) > 0) {
            $qb->andWhere($qb->expr()->in('minute', ':q_minutes'))
                ->setParameter(':q_minutes', $minutes);
        }

        if ($p_state && count($p_state) > 0) {
            $qb->andWhere($qb->expr()->in('control.stateControl', ':q_state'))
                ->setParameter(':q_state', $p_state);
        }

        $qb->select(array(
            'partial control.{id}',
            'partial minute.{id, dateOpening}',
        ));

        return $qb->getQuery()->getResult();
    }

    /**
     * Stat for overall minutes reports
     */
    public function statControl($stateControl = null): mixed
    {
        $qb = $this->queryControlSimple();

        if ($stateControl != null && count($stateControl) > 0) {
            $qb->andWhere($qb->expr()->in('control.stateControl', ':q_stateControl'))
                ->setParameter(':q_stateControl', $stateControl);
        }

        $qb->select(array(
            'partial control.{id, stateControl}',
        ));

        return $qb->getQuery()->getResult();
    }

    /*******************************************************************************************/
    /********************* Custom findAll methods *****/
    /*******************************************************************************************/

    /**
     * Method used to find all closed folders with geo code in a specific area
     */
    public function findAllInArea($minLat, $maxLat, $minLon, $maxLon, Adherent $adherent = null, $maxResults = null, $minutes = null): mixed
    {
        $qb = $this->getLocalized($adherent);

        $qb->andWhere($qb->expr()->between('plot.latitude', ':q_minLat', ':q_maxLat'))
            ->andWhere($qb->expr()->between('plot.longitude', ':q_minLon', ':q_maxLon'))
            ->setParameter('q_minLat', $minLat)
            ->setParameter('q_maxLat', $maxLat)
            ->setParameter('q_minLon', $minLon)
            ->setParameter('q_maxLon', $maxLon);

        if ($minutes && count($minutes) > 0) {
            $qb->andwhere($qb->expr()->in('control.minute', ':q_minutes'))
                ->setParameter(':q_minutes', $minutes);
        }

        if ($maxResults) {
            $qb->groupBy('control');
            $qb->setMaxResults($maxResults);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Method used to find all minutes with geo code
     */
    public function findAllWithGeocodeDashboard(?Adherent $adherent = null): array
    {
        $qb = $this->getLocalized($adherent);

        $qb->select([
            'partial control.{id, dateControl}',
            'partial minute.{id, num}',
            'partial humansByControl.{id, name, firstname}',
            'partial humansByMinute.{id,name,firstname}',
            'partial plot.{id,latitude,longitude, address, place, parcel}',
            'partial plot_town.{id,name,code}',
            'partial agent.{id,name,firstname}',
        ]);

        return $qb->getQuery()->getResult();
    }

    /**
     * Method used to find all minutes with geo code
     */
    public function findAllWithGeocode(?Adherent $adherent = null): array
    {
        $qb = $this->getLocalized($adherent);

        return $qb->getQuery()->getResult();
    }

    /**
     * Find Control linked to a Minute entity
     */
    public function findByMinute(Minute $minute): array
    {
        $qb = $this->queryControl();

        $qb->where($qb->expr()->eq('minute', ':q_minute'))
            ->setParameter(':q_minute', $minute);

        return $qb->getQuery()->getResult();
    }

    /*******************************************************************************************/
    /********************* Custom find for test methods *****/
    /*******************************************************************************************/
    /**
     * Override findAll method
     * with Courier dependencies
     */
    public function findOneForTest($type): ?object
    {
        $qb = $this->queryControl();

        $qb->where($qb->expr()->isNull('adherent.logo'));

        $qb->andWhere($qb->expr()->eq('control.type', ':q_type'))
            ->setParameter(':q_type', $type);

        $qb->setMaxResults(1);

        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            echo 'NonUniqueResultException has been thrown - Folder Repository - ' . $e->getMessage();

            return null;
        }
    }

    /*******************************************************************************************/
    /********************* Override basic methods *****/
    /*******************************************************************************************/

    /**
     * Override findAll method
     * with Control dependencies
     */
    public function findAll(): array
    {
        $qb = $this->queryControl();

        return $qb->getQuery()->getResult();
    }

    /**
     * Override find method
     * with Control dependencies
     */
    public function find(mixed $id, $lockMode = null, $lockVersion = null): ?object
    {
        $qb = $this->queryControl();

        $qb->where($qb->expr()->eq('control.id', ':q_control'))
            ->setParameter(':q_control', $id);

        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            echo 'NonUniqueResultException has been thrown - Control Repository - ' . $e->getMessage();

            return null;
        }
    }

    /*******************************************************************************************/
    /********************* Get specifics queries *****/
    /*******************************************************************************************/

    /**
     * Get folder with geo code and with closure date and by adherent
     */
    private function getLocalized(?Adherent $adherent = null): QueryBuilder
    {
        $qb = $this->queryControl();

        $qb->where($qb->expr()->isNotNull('plot.latitude'))
            ->andWhere($qb->expr()->isNotNull('plot.longitude'));

        if ($adherent) {
            if ($adherent->getIntercommunal()) {
                $qb->andWhere($qb->expr()->eq('plot_intercommunal', ':q_intercommunal'))
                    ->setParameter('q_intercommunal', $adherent->getIntercommunal());
            }
            elseif ($adherent->getTown()) {
                $qb->andWhere($qb->expr()->eq('plot_town', ':q_town'))
                    ->setParameter(':q_town', $adherent->getTown());
            }
        }

        return $qb;
    }

    /*******************************************************************************************/
    /********************* Query - Dependencies of Control Entity *****/
    /*******************************************************************************************/

    /**
     * Classic dependencies
     */
    private function queryControlSimple(): QueryBuilder
    {
        return $this->createQueryBuilder('control')
            ->leftJoin('control.minute', 'minute')->addSelect('minute');
    }

    /**
     * Classic dependencies
     */
    private function queryControl(): QueryBuilder
    {
        return $this->createQueryBuilder('control')
            ->leftJoin('control.minute', 'minute')->addSelect('minute')
            ->leftJoin('minute.plot', 'plot')->addSelect('plot')
            ->leftJoin('plot.town', 'plot_town')->addSelect('plot_town')
            ->leftJoin('plot_town.intercommunal', 'plot_intercommunal')->addSelect('plot_intercommunal')
            ->leftJoin('minute.adherent', 'adherent')->addSelect('adherent')
            ->leftJoin('control.humansByMinute', 'humansByMinute')->addSelect('humansByMinute')
            ->leftJoin('control.humansByControl', 'humansByControl')->addSelect('humansByControl')
            ->leftJoin('control.agent', 'agent')->addSelect('agent')
            ->leftJoin('control.agentAttendants', 'agentAttendants')->addSelect('agentAttendants')
            ->leftJoin('control.editions', 'editions')->addSelect('editions')
            ->leftJoin('control.folder', 'folder')->addSelect('folder');
    }
}
