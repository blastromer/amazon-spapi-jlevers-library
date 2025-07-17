<?php

namespace Typhoeus\JleversSpapi\Models\MySql;

use Typhoeus\JleversSpapi\Models\MySqlShippingBaseModel;

/**
 * Class KeyProperties
 *
 * This model handles marketplace configurations, including shipping groups,
 * vendors, pricing options, and fulfillment settings.
 */
class MarketConfig extends MySqlShippingBaseModel
{
    /** @var string The database connection name */
    protected $database = 'shipping';

    /** @var string The table associated with the model */
    protected $table = 'amz_marketplace_config';

    /** @var array The attributes that are not mass assignable */
    protected $guarded = [];

    /** @var bool Indicates if the model should be timestamped */
    public $timestamps = true;

    /**
     * Get the marketplace name.
     *
     * @return string
     */
    public function getMarket(): string
    {
        return $this->market;
    }

    /**
     * Get the main vendor.
     *
     * @return string
     */
    public function getMainVendor(): string
    {
        return $this->main_vendor;
    }

    /**
     * Get the secondary vendor.
     *
     * @return string
     */
    public function getSecondaryVendor(): string
    {
        return $this->secondary_vendor;
    }

    /**
     * Get an array of all secondary vendors.
     *
     * @return array
     */
    public function getSecVendorArray(): array
    {
        $secondary = explode(",", $this->other_secondary_vendors);
        $secondary[] = $this->secondary_vendor;
        return $secondary;
    }

    /**
     * Get the regular shipping group for the main vendor.
     *
     * @return string
     */
    public function getMainRegShipGroup(): string
    {
        return $this->main_reg_ship_group;
    }

    /**
     * Get the economy shipping group.
     *
     * @return string
     */
    public function getEconShipGroup(): string
    {
        return $this->economy_ship_group;
    }

    /**
     * Get the freight shipping group.
     *
     * @return string
     */
    public function getFreightShipGroup(): string
    {
        return $this->freight_ship_group;
    }

    /**
     * Get the Prime shipping group for the main vendor.
     *
     * @return string
     */
    public function getMainPrimeShipGroup(): string
    {
        return $this->main_prime_ship_group;
    }

    /**
     * Get the regular shipping buffer for the main vendor.
     *
     * @return int
     */
    public function getMainRegBuffer(): int
    {
        return $this->main_reg_buffer;
    }

    /**
     * Get the Prime shipping buffer for the main vendor.
     *
     * @return int
     */
    public function getMainPrimeBuffer(): int
    {
        return $this->main_prime_buffer;
    }

    /**
     * Get the regular shipping group for the secondary vendor.
     *
     * @return string
     */
    public function getSecRegShipGroup(): string
    {
        return $this->secondary_reg_ship_group;
    }

    /**
     * Get the Prime shipping group for the secondary vendor.
     *
     * @return string
     */
    public function getSecPrimeShipGroup(): string
    {
        return $this->secondary_prime_ship_group;
    }

    /**
     * Get the regular shipping buffer for the secondary vendor.
     *
     * @return int
     */
    public function getSecRegBuffer(): int
    {
        return $this->secondary_reg_buffer;
    }

    /**
     * Get the Prime shipping buffer for the secondary vendor.
     *
     * @return int
     */
    public function getSecPrimeBuffer(): int
    {
        return $this->secondary_prime_buffer;
    }

    /**
     * Check if the given ID belongs to a secondary vendor.
     *
     * @param string $id
     * @return bool
     */
    public function isSecondary(string $id): bool
    {
        if (empty($this->secondary_identifier)) {
            return false;
        }

        $suffixes = explode(",", $this->secondary_identifier);
        foreach ($suffixes as $suf) {
            if (strpos($id, $suf) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the first secondary vendor identifier suffix.
     *
     * @return string
     */
    public function getSecondarySuffix(): string
    {
        if (empty($this->secondary_identifier)) {
            return "";
        }

        $suffixes = explode(",", $this->secondary_identifier);
        return $suffixes[0];
    }

    /**
     * Check if the given shipping group is valid.
     *
     * @param string $shipGroup
     * @return bool
     */
    public function isValidShipGroup(string $shipGroup): bool
    {
        foreach ($this->attributes as $field => $value) {
            if (strpos($field, "ship_group") !== false && $shipGroup === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the Prime update setting.
     *
     * @return bool
     */
    public function updatePrime(): bool
    {
        return $this->update_prime;
    }

    /**
     * Get the Prime channel identifier.
     *
     * @return string
     */
    public function getPrimeChannel(): string
    {
        return $this->prime_channel;
    }

    /**
     * Get the tax rate.
     *
     * @return float
     */
    public function getTax(): float
    {
        return $this->tax;
    }

    /**
     * Check if direct submission is enabled.
     *
     * @return bool
     */
    public function directSubmit(): bool
    {
        return $this->direct_submit;
    }

    /**
     * Check if list price should be used.
     *
     * @return bool
     */
    public function useListPrice(): bool
    {
        return $this->use_list_price;
    }
}
