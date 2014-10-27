STAGE1
======

Using [Vagrant](http://vagrantup.com/):

    $ vagrant plugin install vagrant-hostmanager
    $ vagrant up

Then head to http://stage1.dev/.

Disclaimer
----------

Current code for STAGE1 is __prototype quality__. It's dirty, and I'm really not proud of it. Actually, if someone working for me produced code like this, I'd fire him. Yeah.

And please don't look at the commit history.

The whole codebase is undergoing drastic refactoring to bring its quality up to an acceptable level.

Getting started
---------------

STAGE1 is quite a big project to grasp, so don't worry about understanding each and every detail (even I don't understand everything that's going on sometimes), and rather focus on the parts you're interesting in contributing to. The Vagrant VM should give you a fully functional dev environment (if not, it's a bug).

#### First!

You need to create an application on [Github](https://github.com/settings/applications) or Bitbucket with these settings:

__For Github__

* Application Name: Stage1-dev
* Homepage URL: http://stage1.dev/
* Authorization callback URL: http://stage1.dev/oauth/github

__For Bitbucket__

* @todo

Then, edit your `stage1/app/config/providers.yml` with these parameters:

```yaml
parameters:
    github_client_id: <The Client ID>
    github_client_secret: <The Client Secret>
    github_api_base_url: https://api.github.com
    github_base_url: https://github.com
    bitbucket_key: ~
    bitbucket_secret: ~
    bitbucket_api_base_url: ~
    bitbucket_base_url: ~
```

Next, go to your CLI and create the administrator:

* `php app/console fos:user:create`
* `php app/console fos:user:activate`
* `php app/console fos:user:promote` (and add `ROLE_SUPER_ADMIN`)

And go!

There a few CLI commands that you may find interesting:

* `stage1:github -u <username> <path>`: a non-interactive CLI github client that uses the Access Token associated with the given user

Structure
---------

The directory structure is basically that of a standard Symfony2 project, with a few additions:

* `docker/` holds the Dockerfiles for all STAGE1's base containers
* `fabfile/` has the fabric configuration
* `help/` is the help site, built with [jekyll](http://jekyllrb.com/)
* `node/` has all node related code
* `packer` contains the packer configuration and support files
* `service/` has the daemontools services definitions

Architecture
------------

STAGE1 is composed of the following bricks:

1. A Symfony2 app for the web UI
2. A nodejs server for websockets (using [primusjs](https://github.com/primus/primus))
3. A nodejs server to fetch docker containers logs ([aldis](https://github.com/stage1/aldis))
4. A nodejs reverse-proxy to route URLs to the right containers ([hipache](https://github.com/ubermuda/hipache))
5. A few [RabbitMQ consumers](#rabbitmq-consumers)
6. A MySQL server for all persistent data storage
7. A Redis server for volatile or performance-demanding storage
8. A very basic code analyzer to determine the build steps ([yuhao](https://github.com/stage1/yuhao))

### How it works (rough picture)

#### Lifecycle of a build

When a new push notification is received (through GitHub's push WebHook), a build order is sent through RabbitMQ to whatever builder is currently available. The builder clones and analyzes the project using yuhao and decides whether it should use the [DockerfileStrategy](blob/master/src/App/CoreBundle/Builder/Strategy/DockerfileStrategy.php) (projects with a Dockerfile) or the [DefaultStrategy](blob/master/src/App/CoreBundle/Builder/Strategy/DefaultStrategy.php) (all other cases). The `DockerfileStrategy` is the equivalent of a `docker build`, with a few options to accommodate STAGE1's requirements. The default strategy creates a build container and uses the build script generated by Yuaho. Once built, the resulting container is committed and executed, a route is added for Hipache in Redis and [the post-build listeners](blob/master/src/App/CoreBundle/EventListener/Build/) are executed.

#### Build output

Build output management depends on the strategy. In the DockerfileStrategy, build output is received directly by the docker-php client, and all messages are sent through RabbitMQ from there. In the StandardStrategy, output is received by Aldis, which in turn forwards everything into RabbitMQ. Two consumers are expected to receive this output:

1. the docker-output consumer, that saves all output to a Redis database
2. the websocket consumer, that will forward all output via websocket for live output streaming in the browser

#### RabbitMQ Consumers

List of existing RabbitMQ consumers:

1. `consumer-build`, the actual builder
2. `consumer-kill`, used to kill builds
3. `consumer-stop`, used to stop already running containers
4. `docker-output`, used to save docker containers output to Redis
5. `consumer-project-import`, used to import projects

See the `service/` folder for more informations.

#### Services

In addition to the RabbitMQ consumers, a few other services exist:

1. [aldis](https://github.com/stage1/aldis) is a nodejs server that connects to the docker remote API and forward all containers output to RabbitMQ
2. [hipache](https://github.com/dotcloud/hipache/) is the reverse proxy used to route URLs to their containers (__note__: STAGE1 runs [a patched version of hipache](https://github.com/ubermuda/hipache) to allow arbitrary middleware stacking)
3. `websocket` is the websocket server, written with [primusjs](https://github.com/primus/primus)

See the `service/` folder for more informations.

Repacking the VM
----------------

Using [Packer](http://packer.io/):

**VMware users**:

    $ packer build -only=vmware-iso packer/dev.json

**VirtualBox users**:

    $ packer build -only=virtualbox-iso packer/dev.json

**Then, everyone**:

    $ vagrant box remove stage1/dev
    $ vagrant box add --name stage1/dev packer/boxes/dev.box