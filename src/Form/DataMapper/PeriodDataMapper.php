<?php

declare(strict_types=1);


namespace Andante\PeriodBundle\Form\DataMapper;

use Andante\PeriodBundle\Exception\InvalidArgumentException;
use League\Period\Exception;
use League\Period\Period;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormInterface;

class PeriodDataMapper implements DataMapperInterface
{
    private string $defaultBoundaryType;
    private string $startDateChildName;
    private string $endDateChildName;
    private string $boundaryTypeChildName;
    private bool $allowNull;

    private const BOUNDARY_TYPES = [
        Period::INCLUDE_START_EXCLUDE_END,
        Period::INCLUDE_ALL,
        Period::EXCLUDE_START_INCLUDE_END,
        Period::EXCLUDE_ALL,
    ];

    public function __construct(
        string $defaultBoundaryType = Period::INCLUDE_START_EXCLUDE_END,
        string $startDateChildName = 'startDate',
        string $endDateChildName = 'endDate',
        string $boundaryTypeChildName = 'boundaryType',
        bool $allowNull = true
    ) {
        $this->assertValidBoundaryType($defaultBoundaryType);
        $this->defaultBoundaryType = $defaultBoundaryType;
        $this->startDateChildName = $startDateChildName;
        $this->endDateChildName = $endDateChildName;
        $this->boundaryTypeChildName = $boundaryTypeChildName;
        $this->allowNull = $allowNull;
    }

    private function assertValidBoundaryType(string $boundaryType): void
    {
        if (! in_array($boundaryType, self::BOUNDARY_TYPES, true)) {
            throw new InvalidArgumentException(
                \sprintf(
                    'Invalid boundary type "%s" provided to %s. Choice between: %s',
                    $boundaryType,
                    self::class,
                    \implode(', ', self::BOUNDARY_TYPES)
                )
            );
        }
    }

    /**
     * @param Period|null $viewData
     */
    public function mapDataToForms($viewData, iterable $forms): void
    {
        // there is no data yet, so nothing to prepopulate
        if (null === $viewData) {
            return;
        }

        // invalid data type
        if (! $viewData instanceof Period) {
            throw new UnexpectedTypeException($viewData, Period::class);
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms); // @phpstan-ignore-line

        // initialize form field values
        $forms[$this->startDateChildName]->setData($viewData->getStartDate());
        $forms[$this->endDateChildName]->setData($viewData->getEndDate());
        if (isset($forms[$this->boundaryTypeChildName])) {
            $forms[$this->boundaryTypeChildName]->setData($viewData->getBoundaryType());
        }
    }

    /**
     * @param iterable $forms
     * @param mixed    $viewData
     */
    public function mapFormsToData(iterable $forms, &$viewData): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms); // @phpstan-ignore-line

        $startDate = $forms[$this->startDateChildName]->getData();
        $endDate = $forms[$this->endDateChildName]->getData();
        $boundaryType = isset($forms[$this->boundaryTypeChildName]) ?
            $forms[$this->boundaryTypeChildName]->getData() : $this->defaultBoundaryType;

        $viewData = null;

        if (! $startDate instanceof \DateTimeInterface && $endDate instanceof \DateTimeInterface) {
            $failure = new TransformationFailedException(\sprintf(
                'Start date should be a %s',
                \DateTimeInterface::class
            ));
            $failure->setInvalidMessage(
                'Start date should be valid. {{ startDate }} is not a valid date.',
                ['{{ startDate }}' => json_encode($startDate)]
            );
            throw $failure;
        }

        if (! $endDate instanceof \DateTimeInterface && $startDate instanceof \DateTimeInterface) {
            $failure = new TransformationFailedException(\sprintf(
                'End date should be a %s',
                \DateTimeInterface::class
            ));
            $failure->setInvalidMessage(
                'End date should be valid. {{ endDate }} is not a valid date.',
                ['{{ endDate }}' => json_encode($endDate)]
            );
            throw $failure;
        }

        if ($startDate instanceof \DateTimeInterface && $endDate instanceof \DateTimeInterface) {
            if ($startDate > $endDate) {
                $failure = new TransformationFailedException('Start date should be greater or equals then the end date.');
                $failure->setInvalidMessage('Start date should be greater or equals then the end date.', [
                    '{{ startDate }}' => json_encode($startDate),
                    '{{ endDate }}' => json_encode($endDate),
                ]);
                throw $failure;
            }

            try {
                $viewData = Period::fromDatepoint($startDate, $endDate, $boundaryType);
            } catch (Exception $e) {
                $failure = new TransformationFailedException('Invalid Period', 0, $e);
                $failure->setInvalidMessage('Invalid Period.', [
                    '{{ startDate }}' => json_encode($startDate),
                    '{{ endDate }}' => json_encode($endDate),
                ]);

                throw $failure;
            }
        }

        if (!$this->allowNull && $viewData === null) {
            $failure = new TransformationFailedException('A valid Period is required');
            $failure->setInvalidMessage('A valid Period is required.', [
                '{{ startDate }}' => json_encode($startDate),
                '{{ endDate }}' => json_encode($endDate),
            ]);
            throw $failure;
        }
    }
}
