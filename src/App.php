<?php
/**
 * This file is part of GridEngine-Status
 * Copyright (C) 2016  Wikimedia Foundation and contributors
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tools\GridEngineStatus;

use Wikimedia\Slimapp\AbstractApp;
use Wikimedia\Slimapp\Config;

class App extends AbstractApp {
	/**
	 * Apply settings to the Slim application.
	 *
	 * @param \Slim\Slim $slim Application
	 */
	protected function configureSlim( \Slim\Slim $slim ) {
		$slim->configureMode( 'production', function () use ( $slim ) {
			$slim->config( [
				'debug' => false,
				'log.level' => Config::getStr( 'LOG_LEVEL', 'INFO' ),
			] );

			// Install a custom error handler
			$slim->error( function ( \Exception $e ) use ( $slim ) {
				$errorId = substr( session_id(), 0, 8 ) . '-' .
					substr( uniqid(), -8 );
				$slim->log->critical( $e->getMessage(), [
					'exception' => $e,
					'errorId' => $errorId,
				] );
				$slim->view->set( 'errorId', $errorId );
				$slim->render( 'error.json' );
			} );
		} );

		$slim->configureMode( 'development', function () use ( $slim ) {
			$slim->config( [
				'debug' => true,
				'log.level' => Config::getStr( 'LOG_LEVEL', 'DEBUG' ),
				'view.cache' => false,
			] );
		} );
	}

	/**
	 * Configure inversion of control/dependency injection container.
	 *
	 * @param \Slim\Helper\Set $container IOC container
	 */
	protected function configureIoc( \Slim\Helper\Set $container ) {
		$container->singleton( 'qstat', function ( $c ) {
			return new Qstat();
		} );
	}

	/**
	 * Configure view behavior.
	 *
	 * @param \Slim\View $view Default view
	 */
	protected function configureView( \Slim\View $view ) {
		$view->parserOptions = [
			'charset' => 'utf-8',
			'cache' => $this->slim->config( 'view.cache' ),
			'debug' => $this->slim->config( 'debug' ),
			'auto_reload' => true,
			'strict_variables' => false,
			'autoescape' => true,
		];

		// Install twig parser extensions
		$view->parserExtensions = [
			new \Slim\Views\TwigExtension(),
			new HumanFilters(),
		];

		// Set default view data
		$view->replace( [
			'app' => $this->slim,
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function configureHeaderMiddleware() {
		return [
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Configure routes to be handled by application.
	 *
	 * @param \Slim\Slim $slim Application
	 */
	protected function configureRoutes( \Slim\Slim $slim ) {
		$slim->group( '/',
			function () use ( $slim ) {
				$slim->get( '', function () use ( $slim ) {
					$page = new StatusPage( $slim );
					$page->setQstat( $slim->qstat );
					$page->setTemplate( 'status.html' );
					$page();
				} )->name( 'status' );
				$slim->get( 'api/v1/', function () use ( $slim ) {
					$page = new StatusPage( $slim );
					$page->setQstat( $slim->qstat );
					$page->setTemplate( 'status.json' );
					$page();
				} )->name( 'status' );
			}
		);

		$slim->notFound( function () use ( $slim ) {
			$slim->render( '404.json' );
		} );
	}
}
