<?php

namespace Icinga\Module\Vspheredb\Sync;

use Icinga\Application\Benchmark;
use Icinga\Exception\IcingaException;
use Icinga\Module\Vspheredb\DbObject\BaseVmHardwareDbObject;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VirtualMachine;
use Icinga\Module\Vspheredb\DbObject\VmDiskUsage;
use Icinga\Module\Vspheredb\PropertySet\PropertySet;

class SyncVmDiskUsage
{
    /** @var VCenter */
    protected $vCenter;

    public function __construct(VCenter $vCenter)
    {
        $this->vCenter = $vCenter;
    }

    protected function assertValidDeviceKey($device)
    {
        if (! is_int($device->key)) {
            throw new IcingaException(
                'Got invalid device key "%s", integer expected',
                $device->key
            );
        }
    }

    public function run()
    {
        $vCenter = $this->vCenter;
        $vCenterUuid = $vCenter->get('uuid');
        $result = $vCenter->getApi()->propertyCollector()->collectObjectProperties(
            new PropertySet('VirtualMachine', ['guest.disk']),
            VirtualMachine::getSelectSet()
        );

        Benchmark::measure(sprintf(
            'Got %d VirtualMachines with guest.disk',
            count($result)
        ));

        $connection = $vCenter->getConnection();
        $usage = VmDiskUsage::loadAllForVCenter($vCenter);
        Benchmark::measure(sprintf(
            'Got %d vm_disk_usage objects from DB',
            count($usage)
        ));

        $seen = [];
        foreach ($result as $vm) {
            $uuid = $vCenter->makeBinaryGlobalUuid($vm->id);
            if (! property_exists($vm->{'guest.disk'}, 'GuestDiskInfo')) {
                continue;
            }
            $root = null;
            foreach ($vm->{'guest.disk'}->GuestDiskInfo as $info) {
                $path = $info->diskPath;

                // Workaround for phantom partitions seen by open-vm-tools
                // run by systemd with PrivateTmp=true
                if ($path === '/') {
                    $root = $info;
                } elseif (is_object($root) && in_array($path, ['/tmp', '/var/tmp'])) {
                    if ($info->capacity === $root->capacity
                        && $info->freeSpace === $root->freeSpace
                    ) {
                        continue;
                    }
                }

                $idx = "$uuid$path";
                $seen[$idx] = $idx;
                if (array_key_exists($idx, $usage)) {
                    $usage[$idx]->set('capacity', $info->capacity);
                    $usage[$idx]->set('free_space', $info->freeSpace);
                } else {
                    $usage[$idx] = VmDiskUsage::create([
                        'vm_uuid'      => $uuid,
                        'vcenter_uuid' => $vCenterUuid,
                        'disk_path'    => $path,
                        'capacity'     => $info->capacity,
                        'free_space'   => $info->freeSpace,
                    ], $connection);
                }
            }
        }

        $this->storeObjects($vCenter->getDb(), $usage, $seen);
    }

    /**
     * @param \Zend_Db_Adapter_Abstract $db
     * @param BaseVmHardwareDbObject[] $objects
     * @param $seen
     */
    protected function storeObjects(\Zend_Db_Adapter_Abstract $db, array $objects, $seen)
    {
        $insert = 0;
        $update = 0;
        $delete = 0;
        $db->beginTransaction();
        foreach ($objects as $key => $object) {
            if (! array_key_exists($key, $seen)) {
                $object->delete();
                $delete++;
            } elseif ($object->hasBeenLoadedFromDb()) {
                if ($object->hasBeenModified()) {
                    $update++;
                    $object->store();
                }
            } else {
                $object->store();
                $insert++;
            }
        }

        $db->commit();
        Benchmark::measure("$insert created, $update changed, $delete deleted");
    }
}