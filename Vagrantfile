# -*- mode: ruby -*-
# vim:set ft=ruby:

Vagrant.configure("2") do |config|

    config.hostmanager.enabled = true
    config.hostmanager.manage_host = true

    config.vm.box = "stage1/dev"

    config.vm.hostname = 'stage1.dev'
    config.vm.network :private_network, ip: '192.168.215.42'
    
    config.vm.synced_folder '.', '/var/www/stage1', type: 'nfs'

    config.hostmanager.aliases = %w(stage1.dev www.stage1.dev help.stage1.dev)

    config.vm.provision :shell, :path => 'bin/vagrant-provision'

    config.vm.provider 'vmware_fusion' do |v|
        v.vmx['memsize'] = 1024
        v.vmx['numvcpus'] = 1
    end

    config.vm.provider 'virtualbox' do |v|
        v.customize [ "modifyvm", :id, "--memory", "1024" ]
        v.cpus = 1
        v.name = "stage1"
    end
end
