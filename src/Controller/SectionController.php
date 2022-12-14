<?php

namespace App\Controller;

use App\Entity\Entrada;
use App\Entity\Principal;
use App\Entity\Section;
use App\Entity\SourceApi;
use App\Form\EntradaSectionType;
use App\Form\SectionFormType;
use App\Form\Step\Section\StepOneType;
use App\Form\Step\Section\StepThreeType;
use App\Form\Step\Section\StepTwoType;
use App\Repository\EntradaRepository;
use App\Repository\ModelTemplateRepository;
use App\Repository\SectionRepository;
use App\Service\Handler\SourceApi\HandlerSourceApi;
use App\Service\UploaderHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\QueryException;
use Exception;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class SectionController
 * @package App\Controller
 * @Route("/admin/section")
 */
class SectionController extends BaseController
{

    private $session;
    private $api;

    /**
     * SectionController constructor.
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session, HandlerSourceApi $api)
    {
        $this->session = $session;
        $this->api = $api;
    }

    /**
     * @param SectionRepository $repository
     * @param PaginatorInterface $paginator
     * @param Request $request
     * @return Response
     * @Route("/", name="admin_section_list")
     */
    public function list(SectionRepository $repository, PaginatorInterface $paginator, Request $request): Response
    {
        $seccion = $repository->getSections();
        $secciones = $paginator->paginate(
            $seccion, /* query NOT result */
            $request->query->getInt('page', 1)/*page number*/,
            20/*limit per page*/
        );

        return $this->render('section_admin/list.html.twig', [
            'sections' => $secciones,
        ]);
    }

    /**
     * @param EntityManagerInterface $em
     * @param Request $request
     * @param UploaderHelper $uploaderHelper
     * @return Response
     * @throws Exception
     * @Route("/new", name="admin_section_new")
     * @IsGranted("ROLE_EDITOR")
     */
    public function new(EntityManagerInterface $em, Request $request, UploaderHelper $uploaderHelper): Response
    {
        $form = $this->createForm(SectionFormType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Section $section */
            $section = $form->getData();

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form['imageFile']->getData();

            if ($uploadedFile) {
                $newFilename = $uploaderHelper->uploadEntradaImage($uploadedFile, false);
                $section->setImageFilename($newFilename);
            }

            if ($this->session->get('principal_id')) {
                $principal_id = $this->session->get('principal_id');
                $principal = $em->getRepository(Principal::class)->find($principal_id);
                if ($principal) {
                    $section->addPrincipale($principal);
                }
                $this->session->remove('principal_id');
            }

            $em->persist($section);
            $em->flush();

            $this->addFlash('success', 'Secci??n creada. Gracias por su contribuci??n');

            return $this->redirectToRoute('admin_section_list');
        }

        return $this->render('section_admin/new.html.twig', [
            'sectionForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/edit", name="admin_section_edit", methods={"GET","POST"})
     * @param Request $request
     * @param Section $section
     * @param UploaderHelper $uploaderHelper
     * @return Response
     * @IsGranted("MANAGE", subject="section")
     * @throws Exception
     */
    public function edit(Request $request, Section $section, UploaderHelper $uploaderHelper): Response
    {
        $form = $this->createForm(SectionFormType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form['imageFile']->getData();
            if ($uploadedFile) {
                $newFilename = $uploaderHelper->uploadEntradaImage($uploadedFile, $section->getImageFilename());
                $section->setImageFilename($newFilename);
            }
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('admin_section_list');
        }

        return $this->render('section_admin/edit.html.twig', [
            'section' => $section,
            'sectionForm' => $form->createView(),
        ]);
    }


    /**
     * @Route("/index/{id}", name="admin_index_delete_section", methods={"DELETE"})
     * @param Section $section
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deleteIndexSection(Section $section, EntityManagerInterface $entityManager): Response
    {
        $indexAlameda = $section->getIndexAlamedas();


        $section->removeIndexAlameda($indexAlameda[0]);

        $entityManager->flush();

        return new Response(null, 204);
    }

    /**
     * @Route("/entrada/{id}/{entrada}", name="admin_entrada_delete_section", methods={"DELETE"})
     * @param Section $section
     * @param Entrada $entrada
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deleteEntradaSection(
        Section $section,
        Entrada $entrada,
        EntityManagerInterface $entityManager
    ): Response {

        $section->removeEntrada($entrada);

        $entityManager->flush();

        return new Response(null, 204);
    }

    /**
     * @Route("/principal/{id}/{principal}", name="admin_principal_delete_section", methods={"DELETE"})
     * @param Section $section
     * @param Principal $principal
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deletePrincipalSection(
        Section $section,
        Principal $principal,
        EntityManagerInterface $entityManager
    ): Response {
        if ($principal->getSecciones() !== null) {
            $section->removePrincipale($principal);
        }

        $section->setPrincipal(null);
        $entityManager->flush();

        return new Response(null, 204);
    }

    /**
     * @Route("/muestra/seccion/{id}")
     * @param Section $section
     * @param EntradaRepository $entradaRepository
     * @return Response
     */
    public function mostrarSection(Section $section, EntradaRepository $entradaRepository): Response
    {
        $entradas = $entradaRepository->findAllEntradasBySeccion($section->getId());

        $twig = $section->getModelTemplate().".html.twig";
        $response_api = '';
        $apiSource = null;

        if ($twig == 'api.html.twig') {

            try {
                $apiSource = $this->container->get('doctrine')->getRepository(SourceApi::class)->findBy([
                 'identifier' => $section->getIdentificador()
                ]);

            } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            }
            if ($apiSource) {
                try {
                    $response_api = $this->api->fetchSourceApi($apiSource[0])[0];
                    $response_api['source'] = $apiSource[0]->getBaseUri();


                } catch (ClientExceptionInterface|DecodingExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|RedirectionExceptionInterface $e) {
                }
            }

        }
        $model = 'models/sections/'.$twig;


        return $this->render($model, [
            'entradas' => $entradas,
            'section' => $section,
            'response_api' => $response_api,
        ]);
    }

    /**
     * @Route("/{id}", name="admin_section_show", methods={"GET"})
     * @param Section $section
     * @return Response
     */
    public function show(Section $section): Response
    {
        return $this->render('section_admin/show.html.twig', [
            'section' => $section,
        ]);
    }

    /**
     * @Route("/new/step1", name="admin_section_new_step1", methods={"GET","POST"})
     * @param Request $request
     * @return Response
     * @throws Exception
     * @IsGranted("ROLE_ADMIN")
     */
    public function newStepOne(Request $request): Response
    {
        $section = new Section();
        $form = $this->createForm(StepOneType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $principal = $form['principal']->getData();
            $section->addPrincipale($principal);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($section);
            $entityManager->flush();

            return $this->redirectToRoute('admin_section_new_step2', [
                'id' => $section->getId(),
            ]);
        }

        return $this->render('section_admin/new_step1.html.twig', [
            'section' => $section,
            'sectionForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/new/step2/{id}", name="admin_section_new_step2", methods={"GET","POST"})
     * @param Request $request
     * @param Section $section
     * @param ModelTemplateRepository $modelTemplateRepository
     * @return Response
     * @IsGranted("ROLE_ADMIN")
     */
    public function newStepTwo(
        Request $request,
        Section $section,
        ModelTemplateRepository $modelTemplateRepository
    ): Response {
        $section->setTitle($section->getName());
        $form = $this->createForm(StepTwoType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $model_template_id = $this->session->get('model_template_id');
            if ($model_template_id) {
                $model_template = $modelTemplateRepository->find($model_template_id);
                if ($model_template) {
                    $section->setModelTemplate($model_template);
                }
                $this->session->remove('model_template_id');
            }
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('admin_section_new_step3', [
                'id' => $section->getId(),
            ]);
        }

        return $this->render('section_admin/new_step2.html.twig', [
            'section' => $section,
            'sectionForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/new/step3/{id}", name="admin_section_new_step3", methods={"GET","POST"})
     * @param Request $request
     * @param Section $section
     * @return Response
     * @IsGranted("ROLE_ADMIN")
     */
    public function newStepThree(Request $request, Section $section): Response
    {
        $section->setTitle($section->getName());
        $form = $this->createForm(StepThreeType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('admin_section_show', [
                'id' => $section->getId(),
            ]);
        }

        return $this->render('section_admin/new_step3.html.twig', [
            'section' => $section,
            'sectionForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/test/api/{identifier}", name="admin_section_test_api", methods={"GET","POST"})
     * @param SourceApi $api
     */
    public function getDataSourceApi(SourceApi $api)
    {
            return new JsonResponse($this->api->fetchSourceApi($api));
    }

    /**
     * @Route("/agregarEntrada/{id}", name="section_agregar_entrada", methods={"GET", "POST"})
     * @param Request $request
     * @param Entrada $entrada
     * @param SectionRepository $sectionRepository
     * @return RedirectResponse|Response
     */
    public function agregarSeccion(Request $request, Section $section, EntradaRepository $entradaRepository)
    {
        $form = $this->createForm(EntradaSectionType::class, $entrada);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $id_section = $form->get('section')->getData();
            $seccion = $sectionRepository->find($id_section);
            $entrada->addSection($seccion);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($entrada);
            $entityManager->flush();

            return $this->redirectToRoute('admin_entrada_index');
        }

        return $this->render('admin_entrada/vistaAgregaSection.html.twig', [
            'index' => $entrada,
            'form' => $form->createView(),
        ]);
    }
}
