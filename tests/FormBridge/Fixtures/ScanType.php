<?php

declare(strict_types=1);

namespace App\Tests\FormBridge\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A form with a field FormBridge cannot describe.
 */
final class ScanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('scan', FileType::class);
    }
}
