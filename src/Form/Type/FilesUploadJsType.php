<?php
namespace Habeuk\FileUploaderBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\CallbackTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Habeuk\FileUploaderBundle\Entity\Files;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;

class FilesUploadJsType extends AbstractType {

  private bool $isMultiple = false;

  public function __construct(private EntityManagerInterface $em, private CsrfTokenManagerInterface $csrfTokenManager) {}

  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'required' => false,
      'multiple' => false,
      'max_size' => '10M',
      'type' => 'document',
      'mime_types' => [
        'image/*'
      ],
      'preview_template' => null,
      'text_display_upload' => 'Drag & drop files here or click to browse',
      'required_message' => 'Veuillez sélectionner un fichier'
    ]);

    $resolver->setAllowedTypes('max_size', 'string');
    $resolver->setAllowedTypes('mime_types', 'array');
    $resolver->setAllowedTypes('preview_template', [
      'string',
      'null'
    ]);
  }

  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $constraints = [];
    if ($options['required']) {
      $constraints = $options['multiple'] ? [
        new Assert\Length(minMessage: $options['required_message'], min: 3)
      ] : [
        new Assert\NotBlank(message: $options['required_message'])
      ];
    }
    $builder->add('fids', HiddenType::class, [
      'constraints' => $constraints,
      'attr' => [
        'class' => 'files-upload-js-input',
        'data-max-size' => $options['max_size'],
        'data-mime-types' => implode(",", $options['mime_types']),
        'data-multiple' => $options['multiple'] ? "true" : "false",
        'data-type' => $options['type']
      ]
    ]);

    // Transformer pour convertir entre les IDs et les entités Files
    $builder->addModelTransformer(new CallbackTransformer([
      $this,
      'transform'
    ], [
      $this,
      'reverseTransform'
    ]));
  }

  public function transform(mixed $datas): mixed {
    // De l'entité vers le formulaire (model → view)
    if (empty($datas)) {
      return [
        'fids' => null,
        'files' => []
      ];
    }
    if ($datas instanceof Files) {
      return $datas ? [
        'fids' => $datas->getId(),
        'files' => [
          $datas
        ]
      ] : [
        'fids' => null,
        'files' => []
      ];
    }
    if ($datas instanceof \Doctrine\ORM\PersistentCollection || $datas instanceof \Doctrine\Common\Collections\ArrayCollection) {
      $fids = [];
      $files = [];
      foreach ($datas->toArray() as $index => $data) {
        $fids[$index] = $data->getId();
        $files[$index] = $data;
      }
      return [
        'fids' => \json_encode($fids),
        'files' => $files
      ];
    }
    else {
      throw new \UnexpectedValueException(sprintf('Type de données inattendu : %s', get_debug_type($datas)));
    }
  }

  public function reverseTransform(mixed $data): mixed {
    // Du formulaire vers l'entité (view → model)
    if (! is_array($data) || empty($data['fids'])) {
      return $this->isMultiple ? new ArrayCollection() : null;
    }
    $fids = $data['fids'];
    if (is_numeric($fids)) {
      return $this->em->getRepository(Files::class)->find($fids) ?? null;
    }
    $fids = json_decode($data['fids'], true);
    $files = [];
    if ($fids && is_array($fids)) {
      $files = $this->em->getRepository(Files::class)->findBy([
        'id' => $fids
      ], [
        'id' => 'DESC'
      ]);
    }
    $Collectionfiles = new ArrayCollection($files);
    return $Collectionfiles;
  }

  public function buildView(FormView $view, FormInterface $form, array $options): void {
    $this->isMultiple = $options['multiple'];
    parent::buildView($view, $form, $options);
    $view->vars['attr']['class'] = 'files-upload-js-container';
    $view->vars['attr']['data-max-size'] = $options['max_size'];
    $view->vars['attr']['data-mime-types'] = implode(",", $options['mime_types']);
    $view->vars['attr']['data-type'] = $options['type'];
    $view->vars['attr']['data-csrf-token'] = $this->csrfTokenManager->getToken('files_upload_js')->getValue();
    $view->vars['multiple'] = $options['multiple'];
    $view->vars['text_display_upload'] = $options['text_display_upload'];
  }

  public function getBlockPrefix(): string {
    return 'files_upload_js';
  }
}