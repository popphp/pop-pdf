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
    const AES_128 = 'AES128';
    const AES_256 = 'AES256';

    protected ?string $userPassword           = null;
    protected ?string $ownerPassword          = null;
    protected string $algorithm               = self::AES_256;
    protected ?Permissions $permissions       = null;
    protected ?string $generatedOwnerPassword = null;

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

    public function setUserPassword(?string $password): Security
    {
        $this->userPassword = $password;
        return $this;
    }

    public function getUserPassword(): ?string
    {
        return $this->userPassword;
    }

    public function hasUserPassword(): bool
    {
        return ($this->userPassword !== null);
    }

    public function setOwnerPassword(?string $password): Security
    {
        $this->ownerPassword = $password;
        return $this;
    }

    public function getOwnerPassword(): ?string
    {
        return $this->ownerPassword;
    }

    public function hasOwnerPassword(): bool
    {
        return ($this->ownerPassword !== null);
    }

    public function setAlgorithm(string $algorithm): Security
    {
        $this->algorithm = $algorithm;
        return $this;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function setPermissions(Permissions $permissions): Security
    {
        $this->permissions = $permissions;
        return $this;
    }

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
