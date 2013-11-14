from fabric.api import *
from fabric.colors import *
from time import sleep

env.host_string = 'stage1.io'
env.user = 'root'
env.project_path = '/var/www'
env.use_ssh_config = True
env.rsync_exclude_from = './app/Resources/rsync-exclude.txt'

env.processes = [
    'consumer-build-0',
    'consumer-build-1',
    'consumer-kill',
    'consumer-project-import',
    'websockets',
    'log-fetch'
]

env.log_files = [
    '/var/log/nginx/symfony.*.log',
    '/tmp/log/*.log',
    '%s/app/logs/*.log' % env.project_path,
    '/var/log/syslog',
    '/var/log/php5-fpm.log',
    '/var/log/mysql/error.log',
]

def rm_cache():
    sudo('rm -rf %s/app/cache/*' % env.project_path)

def hipache_start():
    sudo('monit start hipache')

def hipache_stop():
    sudo('monit stop hipache')

def hipache_restart():
    sudo('monit restart hipache')

def llog():
    local('sudo tail -f %s' % ' '.join(env.log_files))

def log():
    sudo('tail -f %s' % ' '.join(env.log_files))

def log_build():
    sudo('tail -f /tmp/log/consumer-build.*.log')

def prepare_assets():
    info('preparing assets')
    local('app/console assetic:dump --env=prod --no-debug')

def provision():
    with settings(warn_only=True):
        if run('test -f /etc/apt/sources.list.d/dotdeb.list').succeeded:
            info('already provisioned, skipping')
            return

    info('provisioning host')

    with cd('/tmp'):
        put('app/Resources/support/config/apt/dotdeb.list', 'apt-dotdeb.list')
        put('app/Resources/support/config/apt/rabbitmq.list', 'apt-rabbitmq.list')
        put('app/Resources/support/config/apt/sources.list', 'apt-sources.list')
        put('app/Resources/support/config/nginx/prod/default', 'nginx-default')
        put('app/Resources/support/config/nginx/prod/htpasswd', 'nginx-htpasswd')
        put('app/Resources/support/config/php/prod.ini', 'php-php.ini')
        put('app/Resources/support/scripts/stage1.sh', 'stage1.sh')

        with settings(warn_only=True):
            if run('test -d /usr/lib/vmware-tools').succeeded:
                put('app/Resources/support/config/rabbitmq/vm.config', 'rabbitmq-rabbitmq.config')

    sudo('bash /tmp/stage1.sh')
    reboot()

def check_connection():
    run('echo "OK"')

def redis_list_frontends():
    run('redis-cli keys frontend:\\*')

def redis_list_auths():
    run('redis-cli keys auth:\\*')

def redis_flushall():
    info('flushing redis')
    run('redis-cli FLUSHALL')

def hipache_init_redis():
    info('initializing redis for hipache')
    run('redis-cli DEL frontend:%s' % env.host_string)
    run('redis-cli RPUSH frontend:%s stage1 http://127.0.0.1:8080/' % env.host_string)

def fix_permissions():
    with settings(warn_only=True):
        if run('test -d %s/app/cache' % env.project_path).succeeded:
            info('fixing cache and logs permissions')
            www_user = run('ps aux | grep -E \'nginx\' | grep -v root | head -1 | cut -d\  -f1')
            sudo('setfacl -R -m u:%(www_user)s:rwX -m u:$(whoami):rwX %(project_path)s/app/cache %(project_path)s/app/logs' % { 'www_user': www_user, 'project_path': env.project_path })
            sudo('setfacl -dR -m u:%(www_user)s:rwX -m u:$(whoami):rwX %(project_path)s/app/cache %(project_path)s/app/logs' % { 'www_user': www_user, 'project_path': env.project_path })


def needs_cold_deploy():
    with settings(warn_only=True):
        return run('test -d %s' % env.project_path).failed

def prepare():
    with settings(warn_only=True):
        if run('test -d %s' % env.project_path).succeeded:
            info('already prepared, skipping')
            return

    info('preparing host')
    sudo('mkdir %s' % env.project_path)
    sudo('chown -R www-data:www-data %s' % env.project_path)

def reset_database():
    processes_stop()
    with cd(env.project_path):
        run('app/console --env=prod doctrine:database:drop --force')
        run('app/console --env=prod doctrine:database:create')
        run('app/console --env=prod doctrine:schema:update --force')
    processes_start()
    docker_clean()

def create_database():
    run('%s/app/console --env=prod doctrine:database:create' % env.project_path)

def init_parameters():
    run('cp %s/app/config/parameters.yml.dist %s/app/config/parameters.yml' % (env.project_path, env.project_path))
    run('cp %s/app/config/github.yml.dist %s/app/config/github.yml' % (env.project_path, env.project_path))

def docker_update():
    docker_build()

def docker_build():
    info('building docker')
    sudo('docker build -t symfony2 %s/docker/symfony2' % env.project_path)

def docker_clean():
    info('cleaning docker')
    sudo('%s/bin/docker/clean.sh' % env.project_path)

def deploy():
    if needs_cold_deploy():
        info('first time deploy')
        cold_deploy()
    else:
        hot_deploy()

def cold_deploy():
    prepare()
    branch = git_branch()
    info('deploying branch "%s"' % branch)

    prepare_deploy(branch)
    tag_release()
    reset_environment()

    prepare_assets()

    processes_stop()
    rsync()

    fix_permissions()

    with cd(env.project_path):
        docker_build()
        init_parameters()
        info('clearing remote cache')
        run('php app/console cache:clear --env=prod --no-debug')
        run('chmod -R 0777 app/cache app/logs')
        create_database()
        info('running database migrations')
        run('php app/console doctrine:schema:update --env=prod --no-debug --force')

    services_restart()
    info('giving monit some time...')
    sleep(2) # give monit some time to be reachable
    processes_start()
    hipache_restart()

    run('chown -R www-data:www-data %s' % env.project_path)
    run('chmod -R 0777 %s/app/cache %s/app/logs' % (env.project_path, env.project_path))

def hot_deploy():
    branch = git_branch()
    info('deploying branch "%s"' % branch)

    prepare_deploy(branch)
    tag_release()
    reset_environment()

    prepare_assets()

    processes_stop()
    rsync()

    with cd(env.project_path):
        info('clearing remote cache')
        run('php app/console cache:clear --env=prod --no-debug')
        run('chmod -R 0777 app/cache app/logs')
        info('running database migrations')
        run('php app/console doctrine:schema:update --env=prod --no-debug --force')

    services_restart()
    info('giving monit some time...')
    sleep(2) # give monit some time to be reachable
    processes_start()
    hipache_restart()
    run('chown -R www-data:www-data %s' % env.project_path)
    run('chmod -R 0777 %s/app/cache %s/app/logs' % (env.project_path, env.project_path))

def info(string):
    print(green('---> %s' % string))

def warning(string):
    print(yellow('---> %s' % string))

def error(string):
    abord(red('---> %s' %string))

def is_release_tagged():
    return len(local('git tag --contains HEAD', capture=True)) > 0

def git_branch():
    return local('git symbolic-ref -q HEAD | sed -e \'s|^refs/heads/||\'', capture=True)

def processes_stop():
    info('stopping processes')
    with settings(warn_only=True):
        for process in env.processes:
            sudo('monit stop %s' % process)

def processes_start():
    info('starting processes')
    with settings(warn_only=True):
        for process in env.processes:
            sudo('monit start %s' % process)

def processes_restart():
    info('restarting processes')
    with settings(warn_only=True):
        for process in env.processes:
            sudo('monit restart %s' % process)

def services_restart():
    sudo('/etc/init.d/nginx restart')
    sudo('/etc/init.d/php5-fpm restart')
    sudo('/etc/init.d/rabbitmq-server restart')
    sudo('/etc/init.d/monit reload')

@runs_once
def prepare_deploy(ref='HEAD'):
    info('checking that the repository is clean')
    with settings(warn_only=True):
        if (1 == local('git diff --quiet --ignore-submodules -- %s' % ref).return_code):
            error('Your repository is not in a clean state.')

        # local('git fetch --quiet origin refs/heads/%(ref)s:refs/remotes/origin/%(ref)s' % {'ref': ref})

        if (1 == local('git diff --quiet --ignore-submodules -- remotes/origin/%s' % ref)):
            error('Please merge origin before deploying')

def diff():
    last_tag = local('git describe --tags $(git rev-list --tags --max-count=1)', capture=True)
    local('git log --pretty=oneline --color %s..' % last_tag)

@runs_once
def tag_release():
    if is_release_tagged():
        info('release is already tagged, skipping')
        return

    tag = local('git describe --tags $(git rev-list --tags --max-count=1) | sed \'s/v//\' | awk -F . \'{ printf "v%d.%d.%d", $1, $2, $3 + 1 }\'', capture=True)
    local('git tag %s' % tag)
    info('tagged version %s' % tag)

    local('git push origin --tags')

@runs_once
def reset_environment():
    info('resetting local environment')
    # local('php app/console cache:clear --env=prod --no-debug --no-warmup')
    # local('php app/console cache:clear --env=dev --no-debug --no-warmup')
    local('composer dump-autoload --optimize')

def rsync():
    info('rsyncing to remote')
    c = "rsync --verbose --rsh=ssh --exclude-from=%(exclude_from)s --progress -crDpLt --force --delete ./ %(user)s@%(host)s:%(remote)s" % {
        'exclude_from': env.rsync_exclude_from,
        'user': env.user,
        'host': env.host_string,
        'remote': env.project_path
    }

    local(c)
