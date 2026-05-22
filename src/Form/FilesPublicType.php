<?php
namespace Habeuk\FileUploaderBundle\Form;

use Habeuk\FileUploaderBundle\Entity\Files;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;

/**
 *
 * @extends AbstractType<Files>
 */
class FilesPublicType extends AbstractType {

  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder->add('publicFile', VichFileType::class, [
      'required' => true,
      'allow_delete' => false,
      'download_uri' => false,
      'label' => false
    ]);
  }

  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'data_class' => Files::class,
      // Désactive temporairement le validateur d'image natif de ton entité Files
      'validation_groups' => [
        'Default'
      ]
    ]);
  }
}