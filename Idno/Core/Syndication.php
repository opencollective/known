<?php

    /**
     * Syndication (or POSSE - Publish Own Site, Share Everywhere) helpers
     *
     * @package idno
     * @subpackage core
     */

    namespace Idno\Core {

        class Syndication extends \Idno\Common\Component
        {

            public $services = array();
            public $accounts = array();
            public $checkers = array(); // Our array of "does user X have service Y enabled?" checkers

            function init()
            {
            }

            function registerEventHooks()
            {
                \Idno\Core\Idno::site()->events()->addListener('syndicate', function (\Idno\Core\Event $event) {

                    $eventdata = $event->data();
                    if (!empty($eventdata['object'])) {
                        $content_type = $eventdata['object']->getActivityStreamsObjectType();
                        if ($services = \Idno\Core\Idno::site()->syndication()->getServices($content_type)) {
                            if ($selected_services = \Idno\Core\Idno::site()->currentPage()->getInput('syndication')) {
                                if (!empty($selected_services) && is_array($selected_services)) {
                                    foreach ($selected_services as $selected_service) {
                                        $eventdata['syndication_account'] = false;
                                        if (in_array($selected_service, $services)) {
                                            site()->queue()->enqueue('default', 'post/' . $content_type . '/' . $selected_service, $eventdata);
                                        } else if ($implied_service = $this->getServiceByAccountString($selected_service)) {
                                            $eventdata['syndication_account'] = $this->getAccountFromAccountString($selected_service);
                                            site()->queue()->enqueue('default', 'post/' . $content_type . '/' . $implied_service, $eventdata);
                                        }
                                    }
                                }
                            }
                        }
                    }

                });
            }

            /**
             * Return an array of the services registered for a particular content type
             * @param $content_type
             * @return array
             */
            function getServices($content_type = false)
            {
                $services = [];

                if (!empty($content_type)) {
                    if (!empty($this->services[$content_type])) {
                        $services = $this->services[$content_type];
                    }
                } else {
                    if (!empty($this->services)) {
                        foreach ($this->services as $service) {
                            $services = array_merge($services, $service);
                        }
                    }

                }

                $services = Idno::site()->triggerEvent('syndication/services/get', ['services' => $services], $services);

                return array_unique($services);
            }

            /**
             * Given an account string (generated by the syndication input buttons), returns the service it's associated with
             * @param $account_string
             * @return bool|int|string
             */
            function getServiceByAccountString($account_string)
            {
                if ($accounts = $this->getServiceAccountsByService()) {
                    foreach ($accounts as $service => $account_list) {
                        foreach ($account_list as $listed_account) {
                            if ($account_string == $service . '::' . $listed_account['username']) {
                                return $service;
                            }
                        }
                    }
                }

                return false;
            }

            /**
             * Retrieve all the account identifiers associated with syndicating to all registered services
             * @return array
             */
            function getServiceAccountsByService()
            {
                $accounts = [];

                if (!empty($this->accounts)) {
                    $accounts = $this->accounts;
                }

                $accounts = Idno::site()->triggerEvent('syndication/accounts/get', ['accounts' => $accounts], $accounts);

                return $accounts;
            }

            /**
             * Given an account string (generated by the syndication input buttons), returns the account portion
             * @param $account_string
             * @return bool|mixed
             */
            function getAccountFromAccountString($account_string)
            {
                if ($service = $this->getServiceByAccountString($account_string)) {
                    return str_replace($service . '::', '', $account_string);
                }

                return false;
            }

            /**
             * Register syndication $service with idno.
             * @param string $service The name of the service.
             * @param callable $checker A function that will return true if the current user has the service enabled; false otherwise
             * @param array $content_types An array of content types that the service supports syndication for
             */
            function registerService($service, callable $checker, $content_types = array('article', 'note', 'event', 'rsvp', 'reply'))
            {
                $service = strtolower($service);
                if (!empty($content_types)) {
                    foreach ($content_types as $content_type) {
                        if (empty($this->services[$content_type])) {
                            $this->services[$content_type] = [];
                        }
                        if (!in_array($service, $this->services[$content_type]) || empty($this->services[$content_type])) {
                            $this->services[$content_type][] = $service;
                        }
                    }
                }
                $this->checkers[$service] = $checker;
                \Idno\Core\Idno::site()->template()->extendTemplate('content/syndication', 'content/syndication/' . $service);
            }

            /**
             * Registers an account on a particular service as being available. The service itself must also have been registered.
             * @param string $service The name of the service.
             * @param string $username The username or user identifier on the service.
             * @param string $display_name A human-readable name for this account.
             * @param array $other_properties An optional list of additional properties to include in the account record
             */
            function registerServiceAccount($service, $username, $display_name, $other_properties=array())
            {
                $service = strtolower($service);
                if (!empty($this->accounts[$service])) {
                    foreach ($this->accounts[$service] as $idx => $account) {
                        if ($account['username'] == $username) {
                            unset($this->accounts[$service][$idx]); // Remove existing entry if it exists, so fresher one can be added
                        }
                    }
                }
                $this->accounts[$service][] = array_merge($other_properties, ['username' => $username, 'name' => $display_name]);
            }

            /**
             * Adds a content type that the specified service will support
             * @param $service
             * @param $content_type
             */
            function addServiceContentType($service, $content_type)
            {
                if (!empty($this->services[$content_type]) && !in_array($service, $this->services[$content_type])) {
                    $this->services[$content_type][] = $service;
                }
            }

            /**
             * Retrieve the user identifiers associated with syndicating to the specified service
             * @param $service
             * @return bool
             */
            function getServiceAccounts($service)
            {
                if (!empty($this->accounts[$service])) {
                    return $this->accounts[$service];
                }

                return false;
            }

            /**
             * Get a list of fully-formatted service::username syndication strings
             * @return array
             */
            function getServiceAccountStrings()
            {
                $strings = [];
                if ($services = $this->getServiceAccountsByService()) {
                    foreach ($services as $service_name => $service) {
                        foreach ($service as $account) {
                            $strings[] = $service_name . '::' . $account['username'];
                        }
                    }
                }

                return $strings;
            }

            /**
             * Get a list of expanded service data
             * @return array
             */
            function getServiceAccountData()
            {
                $data = [];
                if ($services = $this->getServiceAccountsByService()) {
                    foreach ($services as $service_name => $service) {
                        foreach ($service as $account) {
                            $data[] = [
                                'id'      => $service_name . '::' . $account['username'],
                                'name'    => $account['name'],
                                'service' => $service_name
                            ];
                        }
                    }
                }

                return $data;
            }

            //function triggerSyndication

            /**
             * Does the currently logged-in user have service $service?
             * @param $service
             * @return bool
             */
            function has($service)
            {
                if (!array_key_exists($service, $this->checkers)) {
                    return false;
                }
                $checker = $this->checkers[$service];

                return $checker();
            }

        }

    }
