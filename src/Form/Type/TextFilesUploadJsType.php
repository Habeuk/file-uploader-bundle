<?php
namespace Habeuk\FileUploaderBundle\Form\Type;

use Habeuk\FileUploaderBundle\Entity\Files;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 *
 * @template TForm of FormInterface
 */
final class TextFilesUploadJsType extends AbstractType {

  public function __construct(private readonly EntityManagerInterface $em, private readonly CsrfTokenManagerInterface $csrfTokenManager) {}

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
      'required_message' => 'Veuillez sélectionner un fichier',
      // On délègue la validation au niveau du conteneur
      'error_bubbling' => false
    ]);

    $resolver->setAllowedTypes('required', 'bool');
    $resolver->setAllowedTypes('multiple', 'bool');
    $resolver->setAllowedTypes('max_size', 'string');
    $resolver->setAllowedTypes('type', 'string');
    $resolver->setAllowedTypes('mime_types', 'array');
    $resolver->setAllowedTypes('preview_template', [
      'string',
      'null'
    ]);
    $resolver->setAllowedTypes('text_display_upload', 'string');
    $resolver->setAllowedTypes('required_message', 'string');
  }

  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $constraints = [];

    /** @var bool $isRequired */
    $isRequired = $options['required'];
    /** @var bool $isMultiple */
    $isMultiple = $options['multiple'];
    /** @var string $requiredMessage */
    $requiredMessage = $options['required_message'];

    if ($isRequired) {
      // Callback de validation natif : fonctionne parfaitement que ce soit du JSON ou un ID unique
      $constraints[] = new Assert\Callback(static function (mixed $value, \Symfony\Component\Validator\Context\ExecutionContextInterface $context) use (
      $isMultiple, $requiredMessage): void {
        if (null === $value || '' === $value) {
          $context->buildViolation($requiredMessage)->addViolation();
          return;
        }

        if ($isMultiple) {
          /** @var array<mixed> $decoded */
          $decoded = json_decode((string) $value, true);
          if (empty($decoded)) {
            $context->buildViolation($requiredMessage)->addViolation();
          }
        }
      });
    }

    // On crée le champ caché
    $builder->add('fids', HiddenType::class, [
      'constraints' => $constraints,
      'error_bubbling' => false,
      'attr' => [
        'class' => 'files-upload-js-input',
        'data-max-size' => $options['max_size'],
        'data-mime-types' => implode(',', (array) $options['mime_types']),
        'data-multiple' => $isMultiple ? 'true' : 'false',
        'data-type' => $options['type']
      ]
    ]);

    // On attache le ModelTransformer DIRECTEMENT sur le builder parent
    // Le but est de mapper l'objet/collection (Model) à un tableau array{fids: string|null} (View)
    $builder->addModelTransformer(new CallbackTransformer(function (mixed $modelData) use ($isMultiple): array {
      return $this->transformToView($modelData, $isMultiple);
    }, function (mixed $viewData) use ($isMultiple): mixed {
      return $this->reverseTransformToModel($viewData, $isMultiple);
    }));
  }

  /**
   * De l'entité/collection (Model) vers les sous-champs du formulaire (View)
   *
   * @return array{fids: string|int|null}
   */
  private function transformToView(mixed $modelData, bool $isMultiple): array {
    if (empty($modelData)) {
      return [
        'fids' => null
      ];
    }
    if ($modelData instanceof Files) {
      return [
        'fids' => $modelData->getId()
      ];
    }
    if ($modelData instanceof Collection) {
      $fids = [];
      foreach ($modelData as $file) {
        if ($file instanceof Files && null !== $file->getId()) {
          $fids[] = $file->getId();
        }
      }
      return [
        'fids' => ! empty($fids) ? (json_encode($fids) ?: null) : null
      ];
    }
    throw new \UnexpectedValueException(sprintf('Type de données inattendu : %s', get_debug_type($modelData)));
  }

  /**
   * Des données de la vue (View array) vers l'entité ou la collection (Model)
   *
   * @return Files|Collection<int, Files>|null
   */
  private function reverseTransformToModel(mixed $viewData, bool $isMultiple): mixed {
    if (! is_array($viewData) || ! isset($viewData['fids']) || null === $viewData['fids'] || '' === $viewData['fids']) {
      return $isMultiple ? new ArrayCollection() : null;
    }

    /** @var string|int $fidsData */
    $fidsData = $viewData['fids'];

    // Cas 1 : ID unique numérique (mode multiple = false)
    if (is_numeric($fidsData)) {
      /** @var Files|null $file */
      $file = $this->em->getRepository(Files::class)->find((int) $fidsData);
      return $file;
    }

    // Cas 2 : Chaîne JSON (mode multiple = true)
    if (is_string($fidsData)) {
      /** @var array<int>|null $ids */
      $ids = json_decode($fidsData, true);

      if (! is_array($ids) || empty($ids)) {
        return $isMultiple ? new ArrayCollection() : null;
      }

      // Sécurité : On s'assure que tous les IDs du tableau sont bien des entiers
      $cleanIds = array_filter(array_map('intval', $ids));

      /** @var array<Files> $files */
      $files = $this->em->getRepository(Files::class)->findBy([
        'id' => $cleanIds
      ], [
        'id' => 'DESC'
      ]);

      return new ArrayCollection($files);
    }

    return $isMultiple ? new ArrayCollection() : null;
  }

  /**
   *
   * @param FormView<FormView> $view
   * @param FormInterface<FormInterface> $form
   * @param array<string, mixed> $options
   */
  public function buildView(FormView $view, FormInterface $form, array $options): void {
    parent::buildView($view, $form, $options);

    // Récupération sécurisée du token CSRF
    $csrfToken = $this->csrfTokenManager->getToken('files_upload_js')->getValue();

    // Extraction des fichiers déjà associés pour pouvoir afficher leur preview côté Twig/JS
    $filesPreviews = [];
    $formNormalizedData = $form->getData();

    if ($formNormalizedData instanceof Files) {
      $filesPreviews[] = [
        'id' => $formNormalizedData->getId(),
        'name' => $formNormalizedData->getFilename() // Adapte selon tes méthodes réelles
      ];
    }
    elseif ($formNormalizedData instanceof Collection) {
      foreach ($formNormalizedData as $file) {
        if ($file instanceof Files) {
          $filesPreviews[] = [
            'id' => $file->getId(),
            'name' => $file->getFilename()
          ];
        }
      }
    }

    $view->vars['attr'] = array_merge($view->vars['attr'], [
      'class' => 'files-upload-js-container space-y-2',
      'data-max-size' => $options['max_size'],
      'data-mime-types' => implode(',', (array) $options['mime_types']),
      'data-type' => $options['type'],
      'data-csrf-token' => $csrfToken,
      'data-previews' => json_encode($filesPreviews) ?: '[]'
    ]);

    $view->vars['multiple'] = $options['multiple'];
    $view->vars['text_display_upload'] = $options['text_display_upload'];
  }

  public function getBlockPrefix(): string {
    return 'files_upload_js';
  }
}