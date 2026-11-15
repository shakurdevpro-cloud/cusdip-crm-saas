<?php

namespace App\Support;

use App\Models\Company;
use LogicException;

class CurrentCompany
{
    /**
     * The currently active company.
     */
    protected ?Company $company = null;

    /**
     * Set the current company.
     */
    public function set(Company $company): void
    {
        $this->company = $company;
    }

    /**
     * Get the current company.
     */
    public function get(): ?Company
    {
        return $this->company;
    }

    /**
     * Get the current company ID.
     */
    public function id(): ?int
    {
        return $this->company?->id;
    }

    /**
     * Determine whether a company is currently selected.
     */
    public function has(): bool
    {
        return $this->company !== null;
    }

    /**
     * Get the current company or throw an exception.
     */
    public function require(): Company
    {
        if (! $this->has()) {
            throw new LogicException(
                'No active company has been selected.'
            );
        }

        return $this->company;
    }

    /**
     * Clear the current company.
     */
    public function clear(): void
    {
        $this->company = null;
    }
}
