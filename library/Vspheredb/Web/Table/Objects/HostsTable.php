<?php

namespace Icinga\Module\Vspheredb\Web\Table\Objects;

use Icinga\Util\Format;
use dipl\Html\Link;

class HostsTable extends ObjectsTable
{
    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Name'),
            $this->translate('Model'),
            $this->translate('CPU Cores'),
            $this->translate('Memory'),
            $this->translate('VMs'),
        ];
    }

    public function renderRow($row)
    {
        $caption = Link::create(
            $row->object_name,
            'vspheredb/host',
            ['uuid' => bin2hex($row->uuid)]
        );

        $tr = $this::row([
            $caption,
            $row->sysinfo_model,
            sprintf('%d / %d', $row->hardware_cpu_cores, $row->vms_cnt_cpu),
            sprintf(
                '%s / %s',
                Format::bytes($row->hardware_memory_size_mb * 1024 * 1024, Format::STANDARD_IEC),
                Format::bytes($row->vms_memorymb * 1024 * 1024, Format::STANDARD_IEC)
            ),
            $row->running_vms,
        ]);
        $tr->attributes()->add('class', [$row->runtime_power_state, $row->overall_status]);

        return $tr;
    }

    public function prepareQuery()
    {

        $vms = $this->db()->select()->from(
            ['vc' => 'virtual_machine'],
            [
                'cnt'               => 'COUNT(*)',
                'cnt_cpu'           => 'SUM(vc.hardware_numcpu)',
                'memorymb'          => 'SUM(vc.hardware_memorymb)',
                'runtime_host_uuid' => 'vc.runtime_host_uuid',
            ]
        )->group('vc.runtime_host_uuid');

        $query = $this->db()->select()->from(
            ['o' => 'object'],
            [
                'uuid'                    => 'o.uuid',
                'object_name'             => 'o.object_name',
                'overall_status'          => 'o.overall_status',
                'sysinfo_model'           => 'h.sysinfo_model',
                'hardware_cpu_packages'   => 'h.hardware_cpu_packages',
                'hardware_cpu_cores'      => 'h.hardware_cpu_cores',
                'hardware_memory_size_mb' => 'h.hardware_memory_size_mb',
                'runtime_power_state'     => 'h.runtime_power_state',
                'running_vms'             => 'vms.cnt',
                'vms_cnt_cpu'             => 'vms.cnt_cpu',
                'vms_memorymb'            => 'vms.memorymb'
            ]
        )->join(
            ['h' => 'host_system'],
            'o.uuid = h.uuid',
            []
        )->joinLeft(
            ['vms' => $vms],
            'vms.runtime_host_uuid = h.uuid',
            []
        )->order('object_name ASC');
        if ($this->parentUuids) {
            $query->where('o.parent_uuid IN (?)', $this->parentUuids);
        }

        return $query;
    }
}
