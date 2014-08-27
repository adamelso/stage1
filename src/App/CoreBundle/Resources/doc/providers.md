## Implementing a new Provider

All provider code must live inside the `App\CoreBundle\Provider` namespace.

A Provider in Stage1 is composed of 4 different objects:

* a `Provider` object, that must implement at least the `ProviderInterface` interface.
* a `Discoverer`, implementing the `DiscovererInterface`
* an `Importer`, implementing the `ImporterInterface`
* a `Payload` object, with the `PayloadInterface`

See the GitHub provider for example implementation of everything.

### The Provider object

The provider object is reponsible for general-purpose provider actions.

There are currently two additional interfaces that you can use to add functionality to your provider:

* the `OAuthProviderInterface`, for provider that expose an OAuth server
* the `ConfigurableProviderInterface`, if you need runtime configuration for your provider

For example, the GitLab provider will use the `ConfigurableProviderInterface` to ask users where their GitLab instance lives.

#### The Discoverer object

The Discoverer is reponsible for discovering user repositories through the provider.

#### The Importer object

The Importer object does the actual job of importing projects

#### The Payload object

The Payload object is used to translate a provider's webhook payload into an interface that Stage1 can read.