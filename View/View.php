<?php

declare(strict_types=1);

namespace System\View;

use System\Exception\ExceptionHandler;

class View {
	private $theme = null;

	/**
	 * render
	 *
	 * @param string $file
	 * @param array $vars
	 * @param bool $cache
	 *
	 * @return void
	 */
	public function render(string $theme, array $vars = [], bool $cache = false): void {
		[$module, $file] = explode('@', $theme);

		// COMBAK
		// if ($cache === false) {
		// } else {
		// }

		if (is_null($this->theme)) {
			$path = APP_DIR . 'Modules/' . ucfirst($module) . '/Views/' . $file . '.php';
		} else {
			$path = APP_DIR . 'Modules/' . ucfirst($module) . '/Views/' . $this->theme . '/' . $file . '.php';
		}

		$this->import($path, $vars);
	}

	/**
	 * theme
	 *
	 * @param string $theme
	 *
	 * @return self
	 */
	public function theme(string $theme): self {
		$this->theme = $theme;
		return $this;
	}

	/**
	 * import
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return void
	 */
	private function import(string $file, array $data = []): void {
		if (!file_exists($file)) {
			throw new ExceptionHandler('Dosya bulunamadı.', '<b>View : </b>' . $file);
		}

		extract($data);
		require_once $file;
	}
}
