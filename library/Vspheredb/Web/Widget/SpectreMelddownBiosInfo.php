<?php

namespace Icinga\Module\Vspheredb\Web\Widget;

use dipl\Html\Html;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use dipl\Html\BaseElement;

class SpectreMelddownBiosInfo extends BaseElement
{
    protected $tag = 'span';

    protected $defaultAttributes = [
        'class' => 'bios-info'
    ];

    /** @var HostSystem */
    protected $host;

    protected $dellSpectre;

    protected $hpSpectre;

    public function __construct(HostSystem $host)
    {
        $this->host = $host;
        $baseDir = dirname(dirname(dirname(dirname(__DIR__))));
        $dataDir = "$baseDir/sample-data";
        $this->dellSpectre = json_decode(file_get_contents("$dataDir/dell.json"));
        $this->hpSpectre = json_decode(file_get_contents("$dataDir/hp.json"));
    }

    protected function showDell($series, $model, $version, $releaseDate)
    {
        $strVersion = sprintf('%s (%s)', $version, $releaseDate);

        if ($series === 'PowerEdge' && array_key_exists($model, $this->dellSpectre)) {
            $info = $this->dellSpectre->$model;
            if ($info->bios_version) {
                if (version_compare($info->bios_version, $version, '>')) {
                    return [
                        Html::tag('span', ['style' => 'color: red'], $strVersion),
                        ' (Spectre/meltdown requires ',
                        Html::tag('a', ['target' => '_blank', 'href' => $info->bios_link], $info->bios_version),
                        ')'
                    ];
                } else {
                    return [
                        Html::tag('a', ['target' => '_blank', 'href' => $info->bios_link], $strVersion)
                    ];
                }
            } else {
                return [$strVersion, sprintf(' Spectre/Meltdown: %s', $info->hint)];
            }
        }

        return [$strVersion, ' (Spectre/Meltdown: unknown)'];
    }

    protected function showHp($series, $model, $version, $releaseDate)
    {
        $strVersion = sprintf('%s (%s)', $version, $releaseDate);

        if (array_key_exists($model, $this->dellSpectre)) {
            $info = $this->dellSpectre->$model;
            if ($info->bios_version) {
                if (version_compare($info->bios_version, $version, '>')) {
                    return [
                        Html::tag('span', ['style' => 'color: red'], $strVersion),
                        ' (Spectre/meltdown requires ',
                        Html::tag('a', ['target' => '_blank', 'href' => $info->bios_link], $info->bios_version),
                        ')'
                    ];
                } else {
                    return [
                        Html::tag('a', ['target' => '_blank', 'href' => $info->bios_link], $strVersion)
                    ];
                }
            } else {
                return [$strVersion, sprintf(' Spectre/Meltdown: %s', $info->hint)];
            }
        }

        return [$strVersion, ' (Spectre/Meltdown: unknown)'];
    }

    protected function assemble()
    {
        $host = $this->host;
        $version = $host->get('bios_version');
        $date = date('Y-m-d', strtotime($host->get('bios_release_date')));
        $vendor = $host->get('sysinfo_vendor');
        $result = null;
        list($series, $model) = preg_split('/\s/', $host->get('sysinfo_model'), 2);
        if ($vendor === 'Dell Inc.') {
            $result = $this->showDell($series, $model, $version, $date);
        } elseif ($vendor === 'HP') {
            $result = $this->showHp($series, $model, $version, $date);
        }

        if ($result === null) {
            $result = sprintf('%s (%s) (Spectre/Meltdown: unknown)', $version, $date);
        }

        $this->add($result);
    }
}