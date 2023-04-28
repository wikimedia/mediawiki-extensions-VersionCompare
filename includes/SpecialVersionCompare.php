<?php

use Wikimedia\AtEase\AtEase;

class SpecialVersionCompare extends IncludableSpecialPage {

	private bool $hidediff;
	private bool $hidematch;
	private bool $ignoreversion;

	public function __construct() {
		parent::__construct( 'VersionCompare' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		$output->addModules( 'ext.VersionCompare' );

		$url1 = trim( $request->getText( 'url1' ) );
		$url2 = trim( $request->getText( 'url2' ) );
		$this->hidediff = $request->getBool( 'hidediff' );
		$this->hidematch = $request->getBool( 'hidematch' );
		$this->ignoreversion = $request->getBool( 'ignoreversion' );

		if ( !$this->including() ) {
			$formDescriptor = [
				'urlfield1' => [
					'label-message' => 'version-compare-url-field-label-1',
					'help-message' => 'version-compare-url-field-help-1',
					'class' => 'HTMLTextField',
					'default' => $url1,
					'name' => 'url1'
				],
				'urlfield2' => [
					'label-message' => 'version-compare-url-field-label-2',
					'help-message' => 'version-compare-url-field-help-2',
					'class' => 'HTMLTextField',
					'default' => $url2,
					'name' => 'url2'
				],
				'hidedifffield' => [
					'label-message' => 'version-compare-hide-diff-field-label',
					'class' => 'HTMLCheckField',
					'default' => false,
					'name' => 'hidediff'
				],
				'hidematchfield' => [
					'label-message' => 'version-compare-hide-match-field-label',
					'class' => 'HTMLCheckField',
					'default' => false,
					'name' => 'hidematch'
				],
				'ignoreversionfield' => [
					'label-message' => 'version-compare-ignore-version-field-label',
					'class' => 'HTMLCheckField',
					'default' => false,
					'name' => 'ignoreversion'
				],
			];

			$htmlForm =
				HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
			$htmlForm->setMethod( 'get' );
			$htmlForm->prepareForm()->displayForm( false );
		}

		if ( $url1 === '' && $url1 !== $url2 ) {
			$url1 = $GLOBALS['wgServer'] . $GLOBALS['wgScriptPath'] . '/api.php';
		} elseif ( $url2 === '' && $url1 !== $url2 ) {
			$url2 = $GLOBALS['wgServer'] . $GLOBALS['wgScriptPath'] . '/api.php';
		}

		if ( $url1 !== '' && $url2 !== '' ) {
			$info1 = $this->getVersionInfo( $url1 );
			if ( $info1 === null ) {
				$html = Html::element( 'br' ) .
					Html::element( 'p', [ 'class' => 'error' ],
						$this->msg( 'version-compare-url-error', $url1 )->text()
					);
				$output->addHTML( $html );
				return;
			}

			$info2 = $this->getVersionInfo( $url2 );
			if ( $info2 === null ) {
				$html = Html::element( 'br' ) .
					Html::element( 'p', [ 'class' => 'error' ],
						$this->msg( 'version-compare-url-error', $url2 )->text()
					);
				$output->addHTML( $html );
				return;
			}

			$html = $this->compareWikis( $info1, $info2 );
			$output->addHTML( $html );
		}
	}

	private function getVersionInfo( $url ): ?array {
		$query =
			"?action=query&meta=siteinfo&siprop=general%7Cextensions%7Cskins&format=json";
		AtEase::suppressWarnings();
		$ret = file_get_contents( $url . $query );
		AtEase::restoreWarnings();
		if ( $ret === false ) {
			return null;
		}
		$json = json_decode( $ret );
		if ( $json === null ) {
			return null;
		}
		return $this->getVersionArray( $json );
	}

	/**
	 * @var string[]
	 */
	private static array $wikiProperties = [
		'logo',
		'wikiid',
		'servername',
		'generator',
		'git-hash',
		'phpversion',
		'phpsapi',
		'dbtype',
		'dbversion'
	];

	/**
	 * @var string[]
	 */
	private static array $extensionProperties = [
		'version',
		'vcs-system',
		'vcs-version',
		'vcs-date'
	];

	private function getVersionArray( $info ): ?array {
		if ( !property_exists( $info, 'query' ) ||
			!property_exists( $info->query, 'general' ) ||
			!property_exists( $info->query, 'extensions' ) ) {
			return null;
		}
		$ret = [];
		foreach ( self::$wikiProperties as $property ) {
			if ( property_exists( $info->query->general, $property ) ) {
				$ret[$property] = $info->query->general->$property;
			} else {
				$ret[$property] = '';
			}
		}
		$extensions = [];
		foreach ( $info->query->extensions as $extension ) {
			$e = [];
			foreach ( self::$extensionProperties as $property ) {
				if ( property_exists( $extension, $property ) ) {
					$e[$property] = $extension->$property;
				}
			}
			$extensions[$extension->name] = $e;
		}
		$ret['extensions'] = $extensions;
		$ret['extension-count'] = count( $extensions );
		return $ret;
	}

	/**
	 * Get HTML for showing the logo or a message indicating that there is no logo.
	 * @param array $info
	 * @return string HTML string
	 */
	private function getLogo( array $info ): string {
		return $info['logo']
			? Html::element( 'img', [ 'src' => $info['logo'] ] )
			: Html::element( 'em', [], $this->msg( 'version-compare-no-logo' ) );
	}

	private function compareWikis( $info1, $info2 ): string {
		$html = Html::openElement( 'table', [ 'class' => 'version-compare-table' ] );

		$html .= Html::openElement( 'tr' );
		$html .= Html::openElement( 'th' );
		$html .= Html::closeElement( 'th' );
		$html .= Html::openElement( 'th',
			[ 'class' => 'version-compare-wiki-1', 'width' => '35%' ] );
		$html .= $this->getLogo( $info1 );
		$html .= Html::openElement( 'p' );
		$html .= $info1['wikiid'];
		$html .= Html::closeElement( 'p' );
		$html .= Html::openElement( 'p' );
		$html .= $info1['servername'];
		$html .= Html::closeElement( 'p' );
		$html .= Html::closeElement( 'th' );
		$html .= Html::openElement( 'th',
			[ 'class' => 'version-compare-wiki-2', 'width' => '35%' ] );
		$html .= $this->getLogo( $info2 );
		$html .= Html::openElement( 'p' );
		$html .= $info2['wikiid'];
		$html .= Html::closeElement( 'p' );
		$html .= Html::openElement( 'p' );
		$html .= $info2['servername'];
		$html .= Html::closeElement( 'p' );
		$html .= Html::closeElement( 'th' );
		$html .= Html::closeElement( 'tr' );

		$html .= $this->formatRow( 'MediaWiki', $info1, $info2, [ 'generator', 'git-hash' ], null );
		$html .= $this->formatRow( 'PHP', $info1, $info2, [ 'phpversion', 'phpsapi' ], null );
		$html .= $this->formatRow( $this->msg( 'version-compare-database-label' )->text(), $info1, $info2,
			[ 'dbtype', 'dbversion' ], null );

		$extensions = [];
		foreach ( $info1['extensions'] as $key => $value ) {
			$extensions[] = $key;
		}
		foreach ( $info2['extensions'] as $key => $value ) {
			$extensions[] = $key;
		}
		$extensions = array_unique( $extensions );
		asort( $extensions );
		$info1['unique-extension-count'] = 0;
		$info2['unique-extension-count'] = 0;
		foreach ( $extensions as $value ) {
			$extension1 = $info1['extensions'][$value] ?? null;
			if ( $extension1 === null ) {
				$info2['unique-extension-count'] = $info2['unique-extension-count'] + 1;
			}
			$extension1version = $extension1['version'] ?? null;
			$extension2 = $info2['extensions'][$value] ?? null;
			if ( $extension2 === null ) {
				$info1['unique-extension-count'] = $info1['unique-extension-count'] + 1;
			}
			$extension2version = $extension2['version'] ?? null;
			$html .= $this->formatRow( $value, $extension1, $extension2, self::$extensionProperties,
				$extension1 !== null && $extension2 !== null &&
				( $this->ignoreversion || $extension1version === $extension2version ) );
		}

		$html .=
			$this->formatRow( $this->msg( 'version-compare-extension-count-label' )->text(),
				$info1, $info2, [ 'extension-count' ], null );
		$html .=
			$this->formatRow( $this->msg( 'version-compare-unique-extension-count-label' )->text(),
				$info1, $info2, [ 'unique-extension-count' ], null );

		$html .= Html::openElement( 'tr' );
		$html .= Html::openElement( 'th' );
		$html .= Html::openElement( 'p' );
		$html .= $this->msg( 'version-compare-same-extension-count-label' )->text();
		$html .= Html::closeElement( 'p' );
		$html .= Html::closeElement( 'th' );
		$html .= Html::openElement( 'td', [ 'colspan' => 2 ] );
		$html .= Html::openElement( 'p' );
		$html .= $info1['extension-count'] - $info1['unique-extension-count'];
		$html .= Html::closeElement( 'p' );
		$html .= Html::closeElement( 'td' );
		$html .= Html::closeElement( 'tr' );

		$html .= Html::closeElement( 'table' );

		return $html;
	}

	private function formatRow( string $label, ?array $info1, ?array $info2, array $properties, ?bool $match ): string {
		if ( $match !== null ) {
			if ( !$match && $this->hidediff ) {
				return '';
			}
			if ( $match && $this->hidematch ) {
				return '';
			}
		}

		$html = Html::openElement( 'tr' );
		if ( $match === null ) {
			$html .= Html::openElement( 'th' );
		} elseif ( $match ) {
			$html .= Html::openElement( 'th', [ 'class' => 'version-compare-same' ] );
		} elseif ( $info1 === null ) {
			$html .= Html::openElement( 'th', [ 'class' => 'version-compare-wiki-2' ] );
		} elseif ( $info2 === null ) {
			$html .= Html::openElement( 'th', [ 'class' => 'version-compare-wiki-1' ] );
		} else {
			$html .= Html::openElement( 'th', [ 'class' => 'version-compare-different' ] );
		}
		$html .= Html::openElement( 'p' );
		$html .= $label;
		$html .= Html::closeElement( 'p' );
		$html .= Html::closeElement( 'th' );
		$identical = $this->identicalProperties( $info1, $info2, $properties );
		if ( $identical ) {
			$html .= Html::openElement( 'td', [ 'colspan' => 2 ] );
		} else {
			$html .= Html::openElement( 'td' );
		}
		if ( $info1 === null ) {
			$html .= Html::openElement( 'p' );
			$html .= '&empty;';
			$html .= Html::closeElement( 'p' );
		} else {
			$version = '';
			foreach ( $properties as $property ) {
				if ( isset( $info1[$property] ) && $info1[$property] != '' ) {
					$version .= Html::openElement( 'p' );
					$version .= $info1[$property];
					$version .= Html::closeElement( 'p' );
				}
			}
			if ( $version == '' ) {
				$version .= Html::openElement( 'p' );
				$version .= $this->msg( 'version-compare-no-version' )->text();
				$version .= Html::closeElement( 'p' );
			}
			$html .= $version;
		}
		if ( !$identical ) {
			$html .= Html::closeElement( 'td' );
			$html .= Html::openElement( 'td' );
			if ( $info2 === null ) {
				$html .= Html::openElement( 'p' );
				$html .= '&empty;';
				$html .= Html::closeElement( 'p' );
			} else {
				$version = '';
				foreach ( $properties as $property ) {
					if ( isset( $info2[$property] ) && $info2[$property] != '' ) {
						$version .= Html::openElement( 'p' );
						$version .= $info2[$property];
						$version .= Html::closeElement( 'p' );
					}
				}
				if ( $version == '' ) {
					$version .= Html::openElement( 'p' );
					$version .= $this->msg( 'version-compare-no-version' )->text();
					$version .= Html::closeElement( 'p' );
				}
				$html .= $version;
			}
		}
		$html .= Html::closeElement( 'td' );
		$html .= Html::closeElement( 'tr' );

		return $html;
	}

	private function identicalProperties( ?array $info1, ?array $info2, array $properties ): bool {
		if ( $info1 === null || $info2 === null ) {
			return false;
		}
		foreach ( $properties as $property ) {
			if ( isset( $info1[$property] ) || isset( $info2[$property] ) ) {
				if ( !isset( $info1[$property] ) || !isset( $info2[$property] ) ||
					$info1[$property] != $info2[$property] ) {
					return false;
				}
			}
		}
		return true;
	}
}
