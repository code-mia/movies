<?php

namespace App\Controller;
use App\Service\callApi;
use App\Entity\Movies;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\PieChart;
use App\Form\MassImportType;
use SimpleXLSX;
class FilmController extends AbstractController
{

    /**
     * @Route("/", name="home")
     */
    public function home(Request $request, callApi $callApi, EntityManagerInterface $manager): Response
    {
        $repo = $this->getDoctrine()->getRepository(Movies::class);
        $movies = $repo->findAll();

        $form = $this->createFormBuilder()
                    ->add('sortscoreasc', SubmitType::class, ['attr' => [
                        'class' => 'btn btn-secondary'],
                        'label' => 'Score ↑'
                    ])
                    ->add('sortnameasc', SubmitType::class, ['attr' => [
                        'class' => 'btn btn-secondary'],
                        'label' => 'Name ↑'
                    ])
                    ->add('sortscoredesc', SubmitType::class, ['attr' => [
                        'class' => 'btn btn-secondary'],
                        'label' => 'Score ↓'
                    ])
                    ->add('sortnamedesc', SubmitType::class, ['attr' => [
                        'class' => 'btn btn-secondary'],
                        'label' => 'Name ↓'
                    ])
                     ->getForm();
            $form->handleRequest($request);

            if ($form->get('sortnameasc')->isClicked()) {
                $movies = $repo->findBy(array(),array('name' => 'ASC'));
            }
            else if ($form->get('sortscoreasc')->isClicked()) {
                $movies = $repo->findBy(array(),array('score' => 'ASC'));
            }

            else if ($form->get('sortnamedesc')->isClicked()) {
                $movies = $repo->findBy(array(),array('name' => 'DESC'));
            }
            else if ($form->get('sortscoredesc')->isClicked()) {
                $movies = $repo->findBy(array(),array('score' => 'DESC'));
            }

        
        return $this->render('film/home.html.twig', [
            'movies' => $movies,
            'form' => $form ->createView()
        ]);
    }
    /**
     * @Route("/add", name="ajouter")
     */
    public function addMovie(Request $request, callApi $callApi, EntityManagerInterface $manager): Response
    {
        $repo = $this->getDoctrine()->getRepository(Movies::class);
        $movie = new Movies();
        $form = $this->createFormBuilder($movie)
                    -> add('name',null, [
                        'attr' => [
                            'class' => 'form-control',
                            'placeholder' => 'Title'
                        ], 
                    ])
                     ->add('score',ChoiceType::class, [
                        'attr' => [
                            'class' => 'form-control','placeholder' => 'Score'],
                        'choices'  => [
                            '0' => 0,
                            '1' => 1,
                            '2' => 2,
                            '3' => 3,
                            '4' => 4,
                            '5' => 5,
                            '6' => 6,
                            '7' => 7,
                            '8' => 8,
                            '9' => 9,
                            '10'=> 10]])
                    ->add('save', SubmitType::class, ['attr' => [
                        'class' => 'btn btn-secondary'],
                        'label' => 'Submit'
                    ])
                    ->add('mail', null, ['attr' => [
                        'class' => 'form-control','placeholder' => 'Mail'],'mapped' => false],)
                     ->getForm();
            $form->handleRequest($request);
            $erreur = false;
            if ($form-> isSubmitted() && $form->isValid()) {
                $data = $callApi->getMovieByTitle($movie->getName());
                if ($data["Response"]=="True" && !$repo->findByName($data["Title"])){
                    $movie->setName($data["Title"]);
                    $movie->setImage($data["Poster"]);
                    $movie->setPlot($data["Plot"]);
                    $movie->setVotersNumber(1);
                    $manager->persist($movie);
                    $manager->flush();
                    return $this->redirectToRoute('fiche', ['id' => $movie->getId()]);
                }
                else if ($form-> isSubmitted()){
                    
                    $erreur = true;
                }
            }
        return $this->render('film/add.html.twig', [
            'form' => $form ->createView(),
            'erreur' => $erreur
        ]);
    }
    /**
     * @Route("/card/{id}", name="fiche")
     */
    public function movieCard(Request $request, $id, EntityManagerInterface $manager): Response
    {
        $repo = $this->getDoctrine()->getRepository(Movies::class);
        $movie = $repo->find($id);
       
        $formModify = $this->createFormBuilder()
                            ->add('delete', SubmitType::class, ['attr' => [
                                'class' => 'btn btn-secondary'],
                                'label' => 'Delete'
                            ])
                            ->add('password', PasswordType::class, ['attr' => [
                                'class' => 'form-control','placeholder' => 'Password'],
                                
                            ])
                            ->getForm();
            $formModify->handleRequest($request);

            if ($formModify->isSubmitted()) {
                if($formModify->getData()["password"] == $this->getParameter('admin.password')){
                    $em = $this->getDoctrine()->getManager();
                    $em->remove($movie);
                    $em->flush();
                    return $this->redirect($this->generateUrl('home'));
                }
                
            }

        $form = $this->createFormBuilder()
                    ->add('vote', SubmitType::class, ['attr' => [
                        'class' => 'btn btn-secondary'],
                        'label' => 'Vote'
                    ])
                    ->add('score',ChoiceType::class, [
                        'attr' => [
                            'class' => 'form-control','placeholder' => 'Score'],
                        'choices'  => [
                            '0' => 0,
                            '1' => 1,
                            '2' => 2,
                            '3' => 3,
                            '4' => 4,
                            '5' => 5,
                            '6' => 6,
                            '7' => 7,
                            '8' => 8,
                            '9' => 9,
                            '10'=> 10]])

                    ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newVote = $form->getData();
            $oldScore = $movie->getScore();
            $oldVoters = $movie->getVotersNumber();
            $movie->setVotersNumber($oldVoters+1);
            $newVoters = $movie->getVotersNumber();
            $newScore =($oldVoters*$oldScore+intval($newVote['score']))/($newVoters);
            $movie->setScore(round($newScore,0));
            $manager->persist($movie);
            $manager->flush();
        }
 

        return $this->render('film/moviecard.html.twig', [
            'movie' => $movie,
            'form' => $form->createView(),
            'formModify' => $formModify->createView()
        ]);
    }
    /**
     * @Route("/import", name="importer")
     */
    public function importMovie(Request $request,EntityManagerInterface $entityManager): Response
    {
        
        $form = $this->createForm(MassImportType::class);

        $form->handleRequest($request);
        $erreur = "";


        if ($form->isSubmitted() && $form->isValid()) {
            $xclFile = $form->get('fichier_xcl')->getData();
            $xclFile = $xclFile->getRealPath();


            $em = $this->getDoctrine()->getManager();



            if ( $xlsx = SimpleXLSX::parse($xclFile) ) {

                foreach( $xlsx->rows() as $key=> $r ) {
                    $Film= new Movies();

                    $Film->setName($r[1]);
                    $Film->setPlot($r[2]);
                    $Film->setScore((int)$r[3]);
                    $Film->setVotersNumber((int)$r[4]);
                }
                $em->persist($Film);
                $em->flush();

            } else {
                echo SimpleXLSX::parseError();
                return $this->redirectToRoute('movie_new', ['erreur'=>"impossible de récupérer les données"], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('film/import.html.twig', [
            'erreur'=>$erreur,
            'form' => $form->createView(),
        ]);
    }
        /**
     * @Route("/stats", name="statistiques")
     */
    public function stats(): Response
    {
        $repo = $this->getDoctrine()->getRepository(Movies::class);
        $i=0;
        while ($i<=10) {
            $countScores[$i] = $repo->createQueryBuilder('a')
            ->where('a.score ='.$i)
            ->select('count(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
            $i=$i+1;
        }
        
        $pieChart = new PieChart();
        $pieChart->getData()->setArrayToDataTable(
            [['Score list', 'How many times they appear'],
            ['0/10', intval($countScores[0])],
            ['1/10', intval($countScores[1])],
            ['2/10', intval($countScores[2])],
            ['3/10', intval($countScores[3])],
            ['4/10', intval($countScores[4])],
            ['5/10', intval($countScores[5])],
            ['6/10', intval($countScores[6])],
            ['7/10', intval($countScores[7])],
            ['8/10', intval($countScores[8])],
            ['9/10', intval($countScores[9])],
            ['10/10', intval($countScores[10])]
            ]
        );
        $pieChart->getOptions()->setTitle('Score distribution');
        $pieChart->getOptions()->setHeight(500);
        $pieChart->getOptions()->setWidth(900);
        $pieChart->getOptions()->getTitleTextStyle()->setBold(true);
        $pieChart->getOptions()->getTitleTextStyle()->setColor('#000000');
        $pieChart->getOptions()->getTitleTextStyle()->setItalic(true);
        $pieChart->getOptions()->getTitleTextStyle()->setFontName('Arial');
        $pieChart->getOptions()->getTitleTextStyle()->setFontSize(20);

        return $this->render('film/stats.html.twig', array('piechart' => $pieChart));
    }
}
