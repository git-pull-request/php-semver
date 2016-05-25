<?php

/*
 * This file is part of semver/semver.
 *
 * (c) SemVer <https://github.com/git-pull-request>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace SemVer\SemVer;

use SemVer\SemVer\Exception\InvalidArgumentException;

/**
 * Definition of a Version according to Semantic Versioning 2.0.0.
 *
 * @link http://semver.org/
 */
final class Version
{
    /** @var string */
    private $build;
    /** @var int */
    private $major;
    /** @var int */
    private $minor;
    /** @var int */
    private $patch;
    /** @var string */
    private $preRelease;

    /**
     * @param int    $major
     * @param int    $minor
     * @param int    $patch
     * @param string $preRelease
     * @param string $build
     *
     * @throws \SemVer\SemVer\Exception\InvalidArgumentException
     */
    public function __construct(int $major, int $minor, int $patch, string $preRelease = '', string $build = '')
    {
        $this->major      = $major;
        $this->minor      = $minor;
        $this->patch      = $patch;
        $this->preRelease = $preRelease;
        $this->build      = $build;

        if ('' !== $this->preRelease && !preg_match('#^([0-9A-Za-z-]+\.)*[0-9A-Za-z-]+$#', $this->preRelease)) {
            throw new InvalidArgumentException(
                'The pre-release version is not compatible with rule 9 of the specifications.'
            );
        }

        if ('' !== $this->build && !preg_match('#^([0-9A-Za-z-]+\.)*[0-9A-Za-z-]+$#', $this->build)) {
            throw new InvalidArgumentException(
                'The build version is not compatible with rule 10 of the specifications.'
            );
        }
    }

    /** @return string */
    public function __toString() : string
    {
        $str = sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);
        if ('' !== $this->preRelease) {
            $str .= '-'.$this->preRelease;
        }
        if ('' !== $this->build) {
            $str .= '+'.$this->build;
        }

        return $str;
    }

    /**
     * @param string $version
     *
     * @throws InvalidArgumentException
     *
     * @return Version
     */
    public static function fromString(string $version) : Version
    {
        $patternMajor      = '(?P<major>[0-9]+)';
        $patternMinor      = '(?P<minor>[0-9]+)';
        $patternPatch      = '(?P<patch>[0-9]+)';
        $patternPreRelease = '(?:-(?P<prerelease>(?:[0-9A-Za-z-]+\.)*(?:[0-9A-Za-z-]+)))?';
        $patternBuild      = '(?:\+(?P<build>(?:[0-9A-Za-z-]+\.)*(?:[0-9A-Za-z-]+)))?';

        $pattern = '#^'.$patternMajor.'\.'.$patternMinor.'\.'.$patternPatch.$patternPreRelease.$patternBuild.'$#';
        if (!preg_match($pattern, $version, $matches)) {
            throw new InvalidArgumentException(sprintf('The string "%s" does not look like a version.', $version));
        }

        return new static(
            (int) $matches['major'],
            (int) $matches['minor'],
            (int) $matches['patch'],
            $matches['prerelease'] ?? '',
            $matches['build'] ?? ''
        );
    }

    /**
     * @param array $versions
     *
     * @return array
     */
    public static function sort(array $versions) : array
    {
        usort(
            $versions,
            function (Version $a, Version $b) {
                return $a->compare($b);
            }
        );

        return $versions;
    }

    /**
     * @return int
     */
    public function getMajor() : int
    {
        return $this->major;
    }

    /**
     * @return int
     */
    public function getMinor() : int
    {
        return $this->minor;
    }

    /**
     * @return int
     */
    public function getPatch() : int
    {
        return $this->patch;
    }

    /**
     * @return string
     */
    public function getPreRelease() : string
    {
        return $this->preRelease;
    }

    /**
     * @return string
     */
    public function getBuild() : string
    {
        return $this->build;
    }

    /**
     * @param Version $other
     *
     * @return int
     */
    public function compare(Version $other) : int
    {
        $compare = $this->major <=> $other->major;
        if (0 !== $compare) {
            return $compare;
        }
        $compare = $this->minor <=> $other->minor;
        if (0 !== $compare) {
            return $compare;
        }
        $compare = $this->patch <=> $other->patch;
        if (0 !== $compare) {
            return $compare;
        }

        $myPreReleaseIsEmpty    = '' === $this->preRelease;
        $otherPreReleaseIsEmpty = '' === $other->preRelease;
        if ($otherPreReleaseIsEmpty !== $myPreReleaseIsEmpty) {
            return $myPreReleaseIsEmpty ? 1 : -1;
        }

        if (!$myPreReleaseIsEmpty) {
            // need to compare each subversion
            $myPreRelease    = explode('.', $this->preRelease);
            $theirPreRelease = explode('.', $other->preRelease);

            do {
                $myCurrentPreReleasePart    = array_shift($myPreRelease);
                $theirCurrentPreReleasePart = array_shift($theirPreRelease);

                $myCurrentPreReleasePartIsNull    = null === $myCurrentPreReleasePart;
                $theirCurrentPreReleasePartIsNull = null === $theirCurrentPreReleasePart;

                if ($myCurrentPreReleasePartIsNull !== $theirCurrentPreReleasePartIsNull) {
                    return $myCurrentPreReleasePartIsNull ? -1 : 1;
                }

                $mineIsInt  = ctype_digit($myCurrentPreReleasePart) && strpos($myCurrentPreReleasePart, '00') !== 0;
                $theirIsInt = ctype_digit($theirCurrentPreReleasePart) && strpos(
                        $theirCurrentPreReleasePart,
                        '00'
                    ) !== 0;

                if ($mineIsInt !== $theirIsInt) {
                    return $mineIsInt ? -1 : 1;
                }
                if ($mineIsInt) {
                    $myCurrentPreReleasePart    = (int) $myCurrentPreReleasePart;
                    $theirCurrentPreReleasePart = (int) $theirCurrentPreReleasePart;
                }

                $compare = $myCurrentPreReleasePart <=> $theirCurrentPreReleasePart;
                if (0 !== $compare) {
                    return $compare;
                }
            } while (count($myPreRelease) || count($theirPreRelease));
        }

        return 0;
    }

    /**
     * @param Version $other
     *
     * @return bool
     */
    public function equals(Version $other) : bool
    {
        return 0 === $this->compare($other);
    }

    /**
     * @param Version $other
     *
     * @return bool
     */
    public function greaterThan(Version $other) : bool
    {
        return 1 === $this->compare($other);
    }

    /**
     * @param Version $other
     *
     * @return bool
     */
    public function greaterThanOrEqual(Version $other) : bool
    {
        return 0 <= $this->compare($other);
    }

    /**
     * @param Version $other
     *
     * @return bool
     */
    public function lessThan(Version $other) : bool
    {
        return -1 === $this->compare($other);
    }

    /**
     * @param Version $other
     *
     * @return bool
     */
    public function lessThanOrEqual(Version $other) : bool
    {
        return 0 >= $this->compare($other);
    }
}
