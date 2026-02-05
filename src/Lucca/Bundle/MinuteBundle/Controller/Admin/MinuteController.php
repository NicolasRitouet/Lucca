<?php
/*
 * Copyright (c) 2025. Numeric Wave
 *
 * Affero General Public License (AGPL) v3
 *
 * For more information, please refer to the LICENSE file at the root of the project.
 */

namespace Lucca\Bundle\MinuteBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\{RedirectResponse, Response, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;

use Lucca\Bundle\MinuteBundle\Manager\{ControlManager, MinuteManager, MinuteStoryManager, PlotManager};
use Lucca\Bundle\AdherentBundle\Finder\AdherentFinder;
use Lucca\Bundle\DecisionBundle\Entity\Decision;
use Lucca\Bundle\MinuteBundle\Entity\{Minute, MinuteStory, Plot};
use Lucca\Bundle\MinuteBundle\Form\{MinuteBrowserType, MinuteType};
use Lucca\Bundle\MinuteBundle\Generator\NumMinuteGenerator;
use Lucca\Bundle\ParameterBundle\Entity\{Intercommunal, Town};
use Lucca\Bundle\ParameterBundle\Utils\GeneralUtils;
use Lucca\Bundle\SettingBundle\Manager\SettingManager;
use Lucca\Bundle\CoreBundle\Exception\AigleNotificationException;
use Lucca\Bundle\CoreBundle\Service\Aigle\MinuteChangeStatusAigleNotifier;

#[Route('/minute')]
#[IsGranted('ROLE_USER')]
class MinuteController extends AbstractController
{

    /**
     * Filter by rolling year
     * @var bool
     */
    private bool $filterByRollingYear;

    /**
     * Add current adherent to filter
     * @var bool
     */
    private bool $presetAdherentByConnectedUser;


    public function __construct(
        private readonly EntityManagerInterface          $entityManager,
        private readonly MinuteStoryManager              $minuteStoryManager,
        private readonly AdherentFinder                  $adherentFinder,
        private readonly AuthorizationCheckerInterface   $authorizationChecker,
        private readonly GeneralUtils                    $generalUtils,
        private readonly PlotManager                     $plotManager,
        private readonly MinuteManager                   $minuteManager,
        private readonly NumMinuteGenerator              $numMinuteGenerator,
        private readonly MinuteChangeStatusAigleNotifier $minuteChangeStatusAigleNotifier,
        private readonly TranslatorInterface             $translator,
        private readonly ControlManager                  $controlManager
    )
    {
        $this->filterByRollingYear = SettingManager::get('setting.folder.indexFilterByRollingOrCalendarYear.name');
        $this->presetAdherentByConnectedUser = SettingManager::get('setting.folder.presetFilterAdherentByConnectedUser.name');
    }

    #[Route('/', name: 'lucca_minute_index', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function indexAction(Request $request): Response
    {
        $em = $this->entityManager;;

        /** Who is connected ;) */
        $adherent = $this->adherentFinder->whoAmI();

        $adherentTowns = null;
        $adherentIntercommunals = null;

        $filters = array();

        /** if is not admin get all town and intercommunal form adherent*/
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            //  If the adherent is link to a service it's mean he can see all the town
            if ($adherent->getService()) {
                $adherentTowns = $em->getRepository(Town::class)->findAll();
                $adherentIntercommunals = $em->getRepository(Intercommunal::class)->findAll();
            } else {
                $adherentTowns = $this->generalUtils->getTownByAdherents(array($adherent));
                $adherentIntercommunals = $adherent->getIntercommunal() ? array($adherent->getIntercommunal()) : array();
            }
        }

        $form = $this->createForm(MinuteBrowserType::class, null, array(
            'adherent_towns' => $adherentTowns,
            'adherent_intercommunals' => $adherentIntercommunals,
            'allFiltersAvailable' => $adherent->getService() !== null
        ));

        /** Init filters */
        $filters['dateStart'] = new \DateTime($this->filterByRollingYear ? 'last day of this month - 1 year' : 'first day of January');
        $filters['dateEnd'] = new \DateTime($this->filterByRollingYear ? 'last day of this month ' : 'last day of December');
        $filters['num'] = null;
        $filters['status'] = null;
        $filters['adherent_intercommunal'] = null;
        $filters['adherent_town'] = null;


        if ($adherentTowns !== null && count($adherentTowns) === 1)
            $filters['folder_town'] = $adherentTowns;
        else
            $filters['folder_town'] = null;

        if ($adherentIntercommunals !== null && count($adherentIntercommunals) === 1)
            $filters['folder_intercommunal'] = $adherentIntercommunals;
        else
            $filters['folder_intercommunal'] = null;

        //  If the adherent is link to a service it's mean he can see all filters
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN') || $adherent->getService()) {
            $filters['adherent'] = $this->presetAdherentByConnectedUser ? array($adherent) : null;
        } else {
            $filters['adherent'] = array($adherent);
        }
        $filters['service'] = null;

        /** If dateStart is not filled - init it */
        if ($form->get('dateStart')->getData() === null)
            $form->get('dateStart')->setData($filters['dateStart']);
        /** If dateEnd is not filled - init it */
        if ($form->get('dateEnd')->getData() === null)
            $form->get('dateEnd')->setData($filters['dateEnd']);

        if ($form->has('adherent')) {
            /** If adherent is not filled - init it */
            $form->get('adherent')->setData($filters['adherent']);
        }


        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            /** Configure filters with form data */
            $filters['dateStart'] = new \DateTime($form->get('dateStart')->getData()->format('Y-m-d') . ' 00:00');
            $filters['dateEnd'] = new \DateTime($form->get('dateEnd')->getData()->format('Y-m-d') . ' 23:59');

            $filters['num'] = $form->get('num')->getData();
            // For the moment, all minutes don't have a status
            $filters['status'] = $form->get('status')->getData();

            if ($form->has('folder_town'))
                $filters['folder_town'] = $form->get('folder_town')->getData();
            if ($form->has('folder_intercommunal'))
                $filters['folder_intercommunal'] = $form->get('folder_intercommunal')->getData();

            if ($this->authorizationChecker->isGranted('ROLE_ADMIN') || $adherent->getService()) {
                $filters['adherent'] = $form->get('adherent')->getData();
                $filters['service'] = $form->get('service')->getData();
                $filters['adherent_intercommunal'] = $form->get('adherent_intercommunal')->getData();
                $filters['adherent_town'] = $form->get('adherent_town')->getData();
            }

            // check filters interval date
            if (!$this->minuteManager->checkFilters($filters)) {
                $this->addFlash('danger', 'flash.minute.filters_too_large');
                /** Init default filters */
                $filters['dateStart'] = new \DateTime($this->filterByRollingYear ? 'last day of this month - 1 year' : 'first day of January');
                $filters['dateEnd'] = new \DateTime($this->filterByRollingYear ? 'last day of this month ' : 'last day of December');
//                why redirect to route keep form values ??????
//                $this->redirectToRoute('lucca_minute_index');
            }
        }

        /** Get minutes in Repo with filters */
        //  If the adherent is link to a service it's mean he can see all the data
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN') || $adherent->getService())
            $minutes = $em->getRepository(Minute::class)->findMinutesBrowser(null,
                $filters['dateStart'],
                $filters['dateEnd'],
                $filters['num'], $filters['status'], $filters['adherent'], $filters['folder_town'], $filters['folder_intercommunal'], $filters['service'], $filters['adherent_town'], $filters['adherent_intercommunal']);
        else
            $minutes = $em->getRepository(Minute::class)->findMinutesBrowser($adherent,
                $filters['dateStart'], $filters['dateEnd'], $filters['num'], $filters['status'], null, $filters['folder_town'], $filters['folder_intercommunal']);

        return $this->render('@LuccaMinute/Minute/browser.html.twig', array(
            'form' => $form->createView(),
            'filters' => $filters,
            'adherent' => $adherent,
            'minutes' => $minutes
        ));
    }

    #[Route('/new', name: 'lucca_minute_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LUCCA')]
    public function newAction(Request $request): RedirectResponse|Response
    {
        $minute = new Minute();

        /** Who is connected ;) */
        $adherent = $this->adherentFinder->whoAmI();
        if ($adherent)
            $minute->setAdherent($adherent);

        /** Security test - User cannot create Minute if he haven't an Agent Entity created before */
        if (sizeof($adherent->getAgents()) === 0) {
            $this->addFlash('info', 'flash.minute.create_agent_before_minute');
            return $this->redirectToRoute('lucca_myagent_new');
        }

        $em = $this->entityManager;;

        /** If the this action is call by right click on map */
        if (!empty($_SESSION)
            && array_key_exists("addrRoute", $_SESSION) && array_key_exists("addrCode", $_SESSION) && array_key_exists("addrCity", $_SESSION)) {
            $minute->setPlot(new Plot());
            $minute->getPlot()->setAddress($_SESSION["addrRoute"]);
            /** @var Town $town */
            $town = $em->getRepository(Town::class)->findOneBy(['code' => $_SESSION["addrCode"]]);
            if (!$town)
                $town = $em->getRepository(Town::class)->findOneBy(['name' => strtoupper($_SESSION["addrCity"])]);
            $minute->getPlot()->setTown($town);

            /** Clear address data from session */
            unset($_SESSION["addrRoute"]);
            unset($_SESSION["addrCode"]);
            unset($_SESSION["addrCity"]);
        }

        $form = $this->createForm(MinuteType::class, $minute, array(
            'adherent' => $adherent
        ));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() &&
            $this->minuteManager->checkMinute($minute)) {

            $minute->setNum($this->numMinuteGenerator->generate($minute));

            if ($minute->getDateComplaint())
                $minute->setDateOpening($minute->getDateComplaint());

            /** Call geo locator service to set latitude and longitude of plot */
            $plot = $minute->getPlot();
            $this->plotManager->manageLocation($plot);

            if (!$this->authorizationChecker->isGranted('ROLE_ADMIN') &&
                $adherent->getTown() && $adherent->getTown() !== $plot->getTown()) {
                $this->addFlash('warning', 'flash.plot.townNotAuthorize');
            } else {
                $em->persist($minute);
                $em->flush();

                /** update status of the minute */
                $this->minuteStoryManager->manage($minute);
                $em->flush();

                $this->addFlash('success', 'flash.minute.createdSuccessfully');

                try {
                    $this->minuteChangeStatusAigleNotifier->updateAigleMinuteStatus($minute);
                } catch (AigleNotificationException $e) {
                    $this->addFlash('danger', $e->getTranslatedMessage($this->translator));
                }

                return $this->redirectToRoute('lucca_minute_show', array('id' => $minute->getId()));
            }
        }

        return $this->render('@LuccaMinute/Minute/new.html.twig', array(
            'minute' => $minute,
            'adherent' => $adherent,
            'form' => $form->createView(),
        ));
    }

    #[Route('-{id}', name: 'lucca_minute_show', methods: ['GET'])]
    #[IsGranted('ROLE_VISU')]
    public function showAction(Request $request, Minute $minute): RedirectResponse|Response
    {
        /** Who is connected ;) */
        $adherent = $this->adherentFinder->whoAmI();

        /** Check if the adherent can access to the minute */
        if (!$this->minuteManager->checkAccessMinute($minute, $adherent)) {
            $this->addFlash('warning', 'flash.minute.cantAccess');
            return $this->redirectToRoute('lucca_minute_index');
        }

        $em = $this->entityManager;;

        $session = $request->getSession();
        $session->set('refresh', false);

        $deleteForm = $this->createDeleteForm($minute);

        /** Get Decision value */
        $decisions = $em->getRepository(Decision::class)->findDecisionsByMinute($minute);

        /** Verify first if status exist */
        if ($minute->getStatus() === null) {
            $this->minuteManager->updateStatusAction($minute);
            $this->minuteStoryManager->manage($minute);
            $em->flush();

            try {
                $this->minuteChangeStatusAigleNotifier->updateAigleMinuteStatus($minute);
            } catch (AigleNotificationException $e) {
                $this->addFlash('danger', $e->getTranslatedMessage($this->translator));
            }
        }

        /** Get Minute Story to get status of the minute */
        $minuteStory = $em->getRepository(MinuteStory::class)->findLastByMinute($minute)[0];

        return $this->render('@LuccaMinute/Minute/show.html.twig', array(
            'minute' => $minute,
            'minuteStory' => $minuteStory,
            'controlsWithoutRefreshTypeLost' => $this->controlManager->countValidControls($minute),
            'decisions' => $decisions,
            'closure' => $minute->getClosure(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    #[Route('-{id}/edit', name: 'lucca_minute_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LUCCA')]
    public function editAction(Request $request, Minute $minute): RedirectResponse|Response
    {
        /** Who is connected ;) */
        $adherent = $this->adherentFinder->whoAmI();

        /** Check if the adherent can access to the minute */
        if (!$this->minuteManager->checkAccessMinute($minute, $adherent)) {
            $this->addFlash('warning', 'flash.minute.cantAccess');
            return $this->redirectToRoute('lucca_minute_index');
        }

        $editForm = $this->createForm(MinuteType::class, $minute, array(
            'adherent' => $adherent,
        ));

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid() &&
            $this->minuteManager->checkMinute($minute)) {
            $em = $this->entityManager;;

            /** Call geo locator service to set latitude and longitude of plot */
            $plot = $minute->getPlot();
            $this->plotManager->manageLocation($plot);

            if (!$this->authorizationChecker->isGranted('ROLE_ADMIN') &&
                $adherent->getTown() && $adherent->getTown() !== $plot->getTown()) {
                $this->addFlash('warning', 'flash.plot.townNotAuthorize');
            } else {
                $em->persist($minute);
                $em->flush();

                $this->addFlash('info', 'flash.minute.updatedSuccessfully');
                return $this->redirectToRoute('lucca_minute_show', array('id' => $minute->getId()));
            }
        }

        return $this->render('@LuccaMinute/Minute/edit.html.twig', array(
            'minute' => $minute,
            'edit_form' => $editForm->createView(),
        ));
    }

    #[Route('-{id}', name: 'lucca_minute_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_LUCCA')]
    public function deleteAction(Request $request, Minute $minute): RedirectResponse
    {
        $form = $this->createDeleteForm($minute);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->entityManager;;

            $em->remove($minute);
            $em->flush();
            $this->addFlash('success', 'flash.minute.deletedSuccessfully');
        }

        return $this->redirectToRoute('lucca_minute_index');
    }

    /**
     * Creates a form to delete a Minute entity.
     *
     * @param Minute $minute
     * @return FormInterface
     */
    private function createDeleteForm(Minute $minute): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('lucca_minute_delete', array('id' => $minute->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
