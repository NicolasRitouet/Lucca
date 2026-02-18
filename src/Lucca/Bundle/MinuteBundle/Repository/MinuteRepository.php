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
use Lucca\Bundle\CoreBundle\Repository\AdherentRepository;
use Lucca\Bundle\MinuteBundle\Entity\Control;

class MinuteRepository extends EntityRepository
{
    /** Traits */
    use AdherentRepository;

    /*******************************************************************************************/
    /********************* Stats methods *****/
    /*******************************************************************************************/

    /**
     * Stat for overall reports
     * Use on overall Stat
     */
    public function statMinuteOverall($dateStart, $dateEnd,
                                      $adherent = null, $town = null, $interco = null, $service = null, $townAdherent = null): mixed
    {
        $qb = $this->queryMinute();

        $qb->andWhere($qb->expr()->between('minute.dateOpening', ':q_start', ':q_end'))
            ->setParameter(':q_start', $dateStart)
            ->setParameter(':q_end', $dateEnd);

        $qb->orderBy('minute.dateOpening', 'ASC');

        if ($adherent && count($adherent) > 0) {
            $qb->andWhere($qb->expr()->in('adherent', ':q_adherent'))
                ->setParameter(':q_adherent', $adherent);
        }

        /** Filter on minute location */
        if ($town && count($town) > 0) {
            $qb->andWhere($qb->expr()->in('plot_town', ':q_townPlot'))
                ->setParameter(':q_townPlot', $town);
        }

        /** Filter on adherent data */
        if ($townAdherent && count($townAdherent) > 0) {
            $qb->andWhere($qb->expr()->in('town', ':q_townAdherent'))
                ->setParameter(':q_townAdherent', $townAdherent);
        }

        if ($interco && count($interco) > 0) {
            $qb->andWhere($qb->expr()->in('intercommunal', ':q_intercommunal'))
                ->setParameter(':q_intercommunal', $interco);
        }

        if ($service && count($service) > 0) {
            $qb->andWhere($qb->expr()->in('service', ':q_service'))
                ->setParameter(':q_service', $service);
        }

        $qb->select(array(
            'partial minute.{id, num, dateOpening}',
            'partial plot.{id,  parcel, address}',
            'partial plot_town.{id, name}',
            'partial adherent.{id, name, function}',
            'partial town.{id, name}',
            'partial intercommunal.{id, name}',
            'partial service.{id, name, code}',
            'partial controls.{id}',
            'partial folder.{id}',
        ));

        return $qb->getQuery()->getResult();
    }

    /**
     * Stat for overall reports
     * Use on table Stat
     */
    public function statMinutes($dateStart = null, $dateEnd = null,
                                $adherent = null, $town = null, $interco = null, $service = null,
                                $origin = null, $risk = null, $townAdherent = null, $folders = null): mixed
    {
        $qb = $this->queryMinute();

        if ($dateStart != null && $dateEnd != null) {
            $qb->andWhere($qb->expr()->between('minute.dateOpening', ':q_start', ':q_end'))
                ->setParameter(':q_start', $dateStart)
                ->setParameter(':q_end', $dateEnd);
        }

        $qb->orderBy('minute.dateOpening', 'ASC');

        if ($adherent != null && count($adherent) > 0) {
            $qb->andWhere($qb->expr()->in('adherent', ':q_adherent'))
                ->setParameter(':q_adherent', $adherent);
        }

        /** Filter on minute location */
        if ($town && count($town) > 0) {
            $qb->andWhere($qb->expr()->in('plot_town', ':q_townPlot'))
                ->setParameter(':q_townPlot', $town);
        }

        /** Filter on adherent data */
        if ($townAdherent && count($townAdherent) > 0) {
            $qb->andWhere($qb->expr()->in('town', ':q_townAdherent'))
                ->setParameter(':q_townAdherent', $townAdherent);
        }

        if ($interco != null && count($interco) > 0) {
            $qb->andWhere($qb->expr()->in('intercommunal', ':q_intercommunal'))
                ->setParameter(':q_intercommunal', $interco);
        }

        if ($service != null && count($service) > 0) {
            $qb->andWhere($qb->expr()->in('service', ':q_service'))
                ->setParameter(':q_service', $service);
        }

        if ($origin != null && count($origin) > 0) {
            $qb->andWhere($qb->expr()->in('minute.origin', ':q_origin'))
                ->setParameter(':q_origin', $origin);
        }

        if ($risk != null && count($risk) > 0) {
            $qb->andWhere($qb->expr()->in('plot.risk', ':q_risk'))
                ->setParameter(':q_risk', $risk);
        }

        if ($folders != null && count($folders) > 0) {
            $qb->andWhere($qb->expr()->in('folder.id', ':q_folder'))
                ->setParameter(':q_folder', $folders);
        }

        $qb->select(array(
            'partial minute.{id, num, dateOpening, origin}',
            'partial adherent.{id, name, firstname, service, function}',
            'partial service.{id, name}',
            'partial plot.{id,  parcel, address, isRiskZone, risk}',
            'partial plot_town.{id, name}',
            'partial town.{id, name}',
            'partial controls.{id, stateControl}',
            'partial folder.{id, dateClosure, nature}',
            'partial natinfs.{id, num, qualification}',
        ));

        return $qb->getQuery()->getResult();
    }

    /**
     * Stat for overall reports
     * Use on table Stat
     */
    public function statMinutesByAdherents($adherents = null): mixed
    {
        $qb = $this->queryMinute();

        if ($adherents != null && count($adherents) > 0) {
            $qb->andWhere($qb->expr()->in('adherent', ':q_adherent'))
                ->setParameter(':q_adherent', $adherents);
        }

        $qb->select(array(
            'partial minute.{id, num, closure, plot}',
            'partial plot.{id, town}',
            'partial adherent.{id, function, city}',
            'partial town.{id, name, code}',
            'partial plot_town.{id, name, code}',
        ));

        return $qb->getQuery()->getResult();
    }

    /*******************************************************************************************/
    /********************* Custom find methods *****/
    /*******************************************************************************************/

    /**
     * Find all minutes between 2 ids to avoid issues when work on huge database
     */
    public function findAllBetweenId($startId, $endId): mixed
    {
        $qb = $this->queryMinuteCommand();

        $qb->andWhere($qb->expr()->between('minute.id', ':q_startId', ':q_endId'))
            ->setParameter('q_startId', $startId)
            ->setParameter('q_endId', $endId);

        return $qb->getQuery()->getResult();
    }

    /**
     * Method used to find all closed folders with geo code in a specific area and by adherent
     */
    public function findAllInArea($minLat, $maxLat, $minLon, $maxLon, Adherent $adherent = null, $closed = null, $maxResults = null, $minutes = null): mixed
    {
        $qb = $this->getLocalized($adherent, $closed);

        $qb->andWhere($qb->expr()->between('plot.latitude', ':q_minLat', ':q_maxLat'))
            ->andWhere($qb->expr()->between('plot.longitude', ':q_minLon', ':q_maxLon'))
            ->setParameter('q_minLat', $minLat)
            ->setParameter('q_maxLat', $maxLat)
            ->setParameter('q_minLon', $minLon)
            ->setParameter('q_maxLon', $maxLon);

        if ($minutes && count($minutes) > 0) {
            $qb->andwhere($qb->expr()->in('minute.id', ':q_minutes'))
                ->setParameter(':q_minutes', $minutes);
        }

        if ($maxResults) {
            $qb->groupBy('minute');
            $qb->setMaxResults($maxResults);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Method used to find all minutes with geo code and by adherent
     */
    public function findAllWithGeocodeDashboard(?Adherent $adherent = null, $stateClosed = null): array
    {
        $qb = $this->getLocalized($adherent, $stateClosed);

        $qb->select('minute.num, minute.id, minute.dateComplaint,
        plot.latitude as plotLat, plot.longitude as plotLng, plot.address as plotAddr, plot.place as plotPlace,
        plot.parcel as plotParcel, plot_town.name as plotTownName, plot_town.code as plotTownCode,
        controls.id as ctrlsId, updatings.id as updatingsId, decisions.id as decisionsId,
        agent.name as agentName, agent.firstname as agentFirstname');

        $qb->groupBy('minute.num');

        return $qb->getQuery()->getResult();
    }

    /**
     * Method used to find all minutes with geo code and by adherent
     */
    public function findAllSpottingWithGeocode(Adherent $adherent = null, $stateClosed = null): array
    {
        $qb = $this->getLocalized($adherent, $stateClosed);

        $qb->andWhere($qb->expr()->eq('SIZE(minute.controls)', 0))
            ->andWhere($qb->expr()->eq('SIZE(minute.updatings)', 0))
            ->andWhere($qb->expr()->eq('SIZE(minute.decisions)', 0));

        return $qb->getQuery()->getResult();
    }

    /**
     * Find Minutes with browser's filters.
     * Used on Minute browser view
     *
     * @param Adherent|null $adherent
     * @param null $dateStart
     * @param null $dateEnd
     * @param null $num
     * @param null $status
     * @param null $p_adherent
     * @param null $town
     * @param null $interco
     * @param null $service
     * @param null $towns
     * @param null $intercos
     * @return mixed
     */
    public function findMinutesBrowser(
        Adherent $adherent = null, $dateStart = null, $dateEnd = null, $num = null, $status = null,
                 $p_adherent = null, $town = null, $interco = null, $service = null, $towns = null, $intercos = null
    ): mixed
    {
        $qb = $this->queryMinuteBrowser();

        $qb->andWhere($qb->expr()->between('minute.dateOpening', ':q_start', ':q_end'))
            ->setParameter(':q_start', $dateStart)
            ->setParameter(':q_end', $dateEnd);

        $qb->orderBy('minute.dateOpening', 'ASC');

        if ($num) {
            $qb->andWhere($qb->expr()->like('minute.num', ':q_num'))
                ->setParameter(':q_num', '%' . $num . '%');
        }

        if ($status && count($status) > 0) {
            $qb->andWhere($qb->expr()->in('minute.status', ':q_status'))
                ->setParameter(':q_status', $status);
        }

        /** use xor to enter only if have $p_ftown or $p_finterco but not both */
        if ($town && count($town) > 0 xor $interco && count($interco) > 0) {

            if ($town && count($town) > 0) {
                $qb->andWhere(
                    $qb->expr()->in('plot_town', ':q_folder_town'))
                    ->setParameter(':q_folder_town', $town);
            }

            if ($interco && count($interco) > 0) {
                $qb->andWhere($qb->expr()->in('plot_intercommunal', ':q_folder_intercommunal'))
                    ->setParameter(':q_folder_intercommunal', $interco);
            }
        } else if ($town && count($town) > 0 && $interco && count($interco) > 0) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in('plot_intercommunal', ':q_folder_intercommunal'),
                    $qb->expr()->in('plot_town', ':q_folder_town')
                ))
                ->setParameter(':q_folder_town', $town)
                ->setParameter(':q_folder_intercommunal', $interco);
        }

        if ($adherent) {
            // Call Trait
            $qb = $this->getValuesAdherent($adherent, $qb);
        } else {
            if ($p_adherent && count($p_adherent) > 0) {
                $qb->andWhere($qb->expr()->in('adherent', ':q_adherent'))
                    ->setParameter(':q_adherent', $p_adherent);
            }

            /** use xor to enter only if have $p_atown or $p_ainterco but not both */
            if ($towns && count($towns) > 0 xor $intercos && count($intercos) > 0) {
                if ($towns && count($towns) > 0) {
                    $qb->andWhere(
                        $qb->expr()->in('town', ':q_adherent_town')
                    )
                        ->setParameter(':q_adherent_town', $towns);
                }

                if ($intercos && count($intercos) > 0) {
                    $qb->andWhere($qb->expr()->in('intercommunal', ':q_adherent_intercommunal'))
                        ->setParameter(':q_adherent_intercommunal', $intercos);
                }
            } else if ($towns && count($towns) > 0 && $intercos && count($intercos) > 0) {
                $qb->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->in('intercommunal', ':q_adherent_intercommunal'),
                        $qb->expr()->in('town', ':q_adherent_town')
                    ))
                    ->setParameter(':q_adherent_town', $towns)
                    ->setParameter(':q_adherent_intercommunal', $intercos);
            }

            if ($service && count($service) > 0) {
                $qb->andWhere($qb->expr()->in('service', ':q_service'))
                    ->setParameter(':q_service', $service);
            }

        }

        $qb->select([
            'partial user.{id, username}',
            'partial adherent.{id, name}',
            'partial minute.{id, num, dateOpening}',
            'partial plot.{id, parcel, latitude, longitude}',
            'partial plot_town.{id, name}',
            'partial plot_intercommunal.{id, name}',
            'partial humans.{id, name, firstname}',
            'partial closure.{id, dateClosing}',
            'partial decisions.{id}',
            'partial tribunalCommission.{id, dateJudicialDesision, statusDecision}',
            'partial appealCommission.{id, dateJudicialDesision, statusDecision}',
            'partial controls2.{id}',
            'partial folder3.{id, type, dateClosure}',
            'partial tagsNature3.{id, name}',
            'partial updatings.{id}',
            'partial controls62.{id}',
            'partial folder63.{id, dateClosure, num}',
            'partial service.{id, name}',
            'partial town.{id, name}',
            'partial intercommunal.{id, name}',
        ]);

        return $qb->getQuery()->getResult();
    }

    /**
     * Find a minute entity with Control entity
     * Used on Test Controller
     */
    public function findMinuteByControl(Control $control): mixed
    {
        $qb = $this->queryMinute();

        $qb->where($qb->expr()->in('controls', ':q_control'))
            ->setParameter(':q_control', $control);

        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            echo 'NonUniqueResultException has been thrown - Minute Repository - ' . $e->getMessage();

            return false;
        }
    }

    /**
     * Find max num used for 1 year
     * Use on Code generator
     */
    public function findMaxNumForYear($year): mixed
    {
        $qb = $this->createQueryBuilder('minute');

        $qb->where($qb->expr()->like('minute.num', ':q_num'))
            ->setParameter('q_num', '%' . $year . '%');

        $qb->select($qb->expr()->max('minute.num'));

        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            echo 'NonUniqueResultException has been thrown - Minute Repository - ' . $e->getMessage();

            return false;
        }
    }

    /*******************************************************************************************/
    /********************* Get specifics queries *****/
    /*******************************************************************************************/

    /**
     * Get folder with geo code and with closure date and by adherent and by state (open or closed)
     */
    private function getLocalized(?Adherent $adherent = null, $stateClosed = null): QueryBuilder
    {
        $qb = $this->queryMinute();

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

        if ($stateClosed != null && count($stateClosed) > 0) {
            $qb->andWhere($qb->expr()->in('closure.status', ':q_state'))
                ->setParameter('q_state', $stateClosed);
        }
        else {
            $qb->andWhere($qb->expr()->isNull('closure'));
        }

        return $qb;
    }

    /*******************************************************************************************/
    /********************* Override basic methods *****/
    /*******************************************************************************************/

    /**
     * Override findAll method
     * with Minute dependencies
     */
    public function findAll(): array
    {
        $qb = $this->queryMinuteBrowser();

        return $qb->getQuery()->getResult();
    }

    /**
     * Override find method
     * with Minute dependencies
     */
    public function find(mixed $id, $lockMode = null, $lockVersion = null): ?object
    {
        $qb = $this->queryMinuteShow();

        $qb->where($qb->expr()->eq('minute.id', ':q_minute'))
            ->setParameter(':q_minute', $id);

        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            echo 'NonUniqueResultException has been thrown - Minute Repository - ' . $e->getMessage();

            return null;
        }
    }

    /*******************************************************************************************/
    /********************* Query - Dependencies of Minute Entity *****/
    /*******************************************************************************************/

    /**
     * Classic dependencies
     */
    private function queryMinute(): QueryBuilder
    {
        return $this->createQueryBuilder('minute')
            ->leftJoin('minute.closure', 'closure')->addSelect('closure')
            ->leftJoin('minute.controls', 'controls')->addSelect('controls')
            ->leftJoin('controls.folder', 'folder')->addSelect('folder')
            ->leftJoin('folder.tagsNature', 'tagsNature')->addSelect('tagsNature')
            ->leftJoin('folder.tagsTown', 'tagsTown')->addSelect('tagsTown')
            ->leftJoin('folder.natinfs', 'natinfs')->addSelect('natinfs')
            ->leftJoin('minute.plot', 'plot')->addSelect('plot')
            ->leftJoin('plot.town', 'plot_town')->addSelect('plot_town')
            ->leftJoin('plot_town.intercommunal', 'plot_intercommunal')->addSelect('plot_intercommunal')
            ->leftJoin('minute.adherent', 'adherent')->addSelect('adherent')
            ->leftJoin('adherent.user', 'user')
            ->leftJoin('adherent.town', 'town')
            ->leftJoin('adherent.intercommunal', 'intercommunal')
            ->leftJoin('adherent.service', 'service')
            ->leftJoin('minute.agent', 'agent')->addSelect('agent')
            ->leftJoin('minute.humans', 'humans')->addSelect('humans')
            ->leftJoin('minute.tribunal', 'tribunal')->addSelect('tribunal')
            ->leftJoin('minute.updatings', 'updatings')->addSelect('updatings')
            ->leftJoin('minute.decisions', 'decisions')->addSelect('decisions');
    }

    /**
     * Query for command
     */
    private function queryMinuteCommand(): QueryBuilder
    {
        return $this->createQueryBuilder('minute')
            ->leftJoin('minute.closure', 'closure')->addSelect('closure')
            ->leftJoin('minute.controls', 'controls')->addSelect('controls')
            ->leftJoin('controls.folder', 'folder')->addSelect('folder')
            ->leftJoin('minute.updatings', 'updatings')->addSelect('updatings')
            ->leftJoin('minute.decisions', 'decisions')->addSelect('decisions');
    }

    /**
     * All dependencies to display one Minute Entity
     */
    private function queryMinuteShow(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('minute')
            ->leftJoin('minute.adherent', 'adherent')->addSelect('adherent')
            ->leftJoin('adherent.user', 'user')
            ->leftJoin('adherent.town', 'town')
            ->leftJoin('adherent.intercommunal', 'intercommunal')
            ->leftJoin('adherent.service', 'service');

        $qb->leftJoin('minute.plot', 'plot')->addSelect('plot')
            ->leftJoin('plot.town', 'plot_town')->addSelect('plot_town');

        $qb->leftJoin('minute.agent', 'agent')->addSelect('agent')
            ->leftJoin('minute.humans', 'humans')->addSelect('humans')
            ->leftJoin('minute.tribunal', 'tribunal')->addSelect('tribunal')
            ->leftJoin('minute.closure', 'closure')->addSelect('closure');

        $qb->leftJoin('minute.decisions', 'decisions')->addSelect('decisions')
            ->leftJoin('decisions.tribunal', 'tribunalDeci')->addSelect('tribunalDeci')
            ->leftJoin('decisions.tribunalCommission', 'tribunalCommission')->addSelect('tribunalCommission')
            ->leftJoin('decisions.appealCommission', 'appealCommission')->addSelect('appealCommission');

        $qb->leftJoin('minute.controls', 'controls2')->addSelect('controls2')
            ->leftJoin('controls2.agent', 'agentControl2')->addSelect('agentControl2')
            ->leftJoin('controls2.agentAttendants', 'agentAttendants2')->addSelect('agentAttendants2')
            ->leftJoin('controls2.humansByMinute', 'humansByMinute2')->addSelect('humansByMinute2')
            ->leftJoin('controls2.humansByControl', 'humansByControl2')->addSelect('humansByControl2')
            ->leftJoin('controls2.folder', 'folder3')->addSelect('folder3');

        $qb->leftJoin('minute.updatings', 'updatings')->addSelect('updatings')
            ->leftJoin('updatings.controls', 'controls62')->addSelect('controls62')
            ->leftJoin('controls62.agent', 'agentControl62')->addSelect('agentControl62')
            ->leftJoin('controls62.agentAttendants', 'agentAttendants62')->addSelect('agentAttendants62')
            ->leftJoin('controls62.humansByMinute', 'humansByMinute62')->addSelect('humansByMinute62')
            ->leftJoin('controls62.humansByControl', 'humansByControl62')->addSelect('humansByControl62')
            ->leftJoin('controls62.folder', 'folder63')->addSelect('folder63');

        return $qb;
    }

    /**
     * All dependencies to display one Minute Entity
     */
    private function queryMinuteBrowser(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('minute')
            ->leftJoin('minute.adherent', 'adherent')
            ->leftJoin('adherent.town', 'town')
            ->leftJoin('adherent.intercommunal', 'intercommunal')
            ->leftJoin('adherent.service', 'service')
            ->leftJoin('adherent.user', 'user');

        $qb->leftJoin('minute.plot', 'plot')
            ->leftJoin('plot.town', 'plot_town')
            ->leftJoin('plot_town.intercommunal', 'plot_intercommunal');

        $qb->leftJoin('minute.humans', 'humans')
            ->leftJoin('minute.closure', 'closure');

        $qb->leftJoin('minute.decisions', 'decisions')
            ->leftJoin('decisions.tribunalCommission', 'tribunalCommission')
            ->leftJoin('decisions.appealCommission', 'appealCommission')
            ->leftJoin('decisions.expulsion', 'expulsion')
            ->leftJoin('decisions.demolition', 'demolition');

        $qb->leftJoin('minute.controls', 'controls2')
            ->leftJoin('controls2.folder', 'folder3');


        $qb->leftJoin('folder3.tagsTown', 'tagsTown3')
            ->leftJoin('folder3.tagsNature', 'tagsNature3');

        $qb->leftJoin('minute.updatings', 'updatings')
            ->leftJoin('updatings.controls', 'controls62')
            ->leftJoin('controls62.folder', 'folder63');

        $qb->leftJoin('folder63.tagsTown', 'tagsTown63')
            ->leftJoin('folder63.tagsNature', 'tagsNature63');

        return $qb;
    }
}
