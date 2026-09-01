<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Pdf\Document;

/**
 * Pdf security class
 *
 * @category   Pop
 * @package    Pop\Pdf
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    6.0.0
 */
class Security
{

    /**
     * Supported encryption algorithms
     */
    const AES_128 = 'AES128';
    const AES_256 = 'AES256';

    /**
     * PDF user (open) password
     * @var ?string
     */
    protected ?string $userPassword = null;

    /**
     * PDF owner (permissions) password
     * @var ?string
     */
    protected ?string $ownerPassword = null;

    /**
     * Encryption algorithm, one of the AES_128/AES_256 constants
     * @var string
     */
    protected string $algorithm = self::AES_256;

    /**
     * PDF permission flags
     * @var ?Permissions
     */
    protected ?Permissions $permissions = null;

    /**
     * Randomly-generated owner password, used when the caller never sets one
     * @var ?string
     */
    protected ?string $generatedOwnerPassword = null;

    /**
     * Instantiate the security object
     *
     * @param  ?string      $userPassword
     * @param  ?string      $ownerPassword
     * @param  ?Permissions $permissions
     * @param  string       $algorithm
     */
    public function __construct(
        ?string $userPassword = null, ?string $ownerPassword = null,
        ?Permissions $permissions = null, string $algorithm = self::AES_256
    )
    {
        if ($userPassword !== null) {
            $this->setUserPassword($userPassword);
        }
        if ($ownerPassword !== null) {
            $this->setOwnerPassword($ownerPassword);
        }
        if ($permissions !== null) {
            $this->setPermissions($permissions);
        }
        $this->setAlgorithm($algorithm);
    }

    /**
     * Set the user (open) password
     *
     * @param  ?string $password
     * @return Security
     */
    public function setUserPassword(?string $password): Security
    {
        $this->userPassword = $password;
        return $this;
    }

    /**
     * Get the user (open) password
     *
     * @return ?string
     */
    public function getUserPassword(): ?string
    {
        return $this->userPassword;
    }

    /**
     * Determine whether a user (open) password has been set
     *
     * @return bool
     */
    public function hasUserPassword(): bool
    {
        return ($this->userPassword !== null);
    }

    /**
     * Set the owner (permissions) password
     *
     * @param  ?string $password
     * @return Security
     */
    public function setOwnerPassword(?string $password): Security
    {
        $this->ownerPassword = $password;
        return $this;
    }

    /**
     * Get the owner (permissions) password
     *
     * @return ?string
     */
    public function getOwnerPassword(): ?string
    {
        return $this->ownerPassword;
    }

    /**
     * Determine whether an owner (permissions) password has been set
     *
     * @return bool
     */
    public function hasOwnerPassword(): bool
    {
        return ($this->ownerPassword !== null);
    }

    /**
     * Set the encryption algorithm
     *
     * @param  string $algorithm
     * @return Security
     */
    public function setAlgorithm(string $algorithm): Security
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    /**
     * Get the encryption algorithm
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * Set the permission flags
     *
     * @param  Permissions $permissions
     * @return Security
     */
    public function setPermissions(Permissions $permissions): Security
    {
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Get the permission flags, creating a default all-allowed set if none was configured
     *
     * @return Permissions
     */
    public function getPermissions(): Permissions
    {
        if ($this->permissions === null) {
            $this->permissions = new Permissions();
        }
        return $this->permissions;
    }

    /**
     * The password actually used as the PDF Owner password when computing
     * /O, /U, etc. If the caller never set one, a random password is
     * generated once and cached here - leaving it truly blank would mean
     * anyone could remove restrictions with an empty owner password,
     * silently defeating the permissions feature.
     *
     * @return string
     */
    public function getEffectiveOwnerPassword(): string
    {
        if ($this->hasOwnerPassword()) {
            return $this->ownerPassword;
        }

        if ($this->generatedOwnerPassword === null) {
            $this->generatedOwnerPassword = base64_encode(random_bytes(24));
        }

        return $this->generatedOwnerPassword;
    }
}
