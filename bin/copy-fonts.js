/**
 * Copia los WOFF2 de Inter y JetBrains Mono (paquetes @fontsource, licencia
 * SIL OFL) a assets/fonts/ y genera assets/css/fonts.css con @font-face.
 *
 * Se auto-alojan las fuentes para que wp-admin nunca llame a Google Fonts:
 * Profit Lens no debe hacer requests salientes con datos de la tienda ni
 * siquiera para tipografía.
 *
 * Uso: npm run fonts (correr una vez tras `npm install`, o cuando cambien
 * los pesos/pines de versión de @fontsource).
 */
const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '..' );
const FONTS_OUT = path.join( ROOT, 'assets/fonts' );
const CSS_OUT = path.join( ROOT, 'assets/css/fonts.css' );

const FONTS = [
	{
		pkg: '@fontsource/inter',
		family: 'Inter',
		prefix: 'inter-latin',
		weights: [ 400, 500, 600 ],
	},
	{
		pkg: '@fontsource/jetbrains-mono',
		family: 'JetBrains Mono',
		prefix: 'jetbrains-mono-latin',
		weights: [ 400, 500, 600 ],
	},
];

fs.mkdirSync( FONTS_OUT, { recursive: true } );

let css =
	'/**\n * Generado por bin/copy-fonts.js — no editar a mano.\n' +
	' * Inter y JetBrains Mono, SIL Open Font License 1.1.\n */\n\n';

for ( const font of FONTS ) {
	const filesDir = path.join( ROOT, 'node_modules', font.pkg, 'files' );

	for ( const weight of font.weights ) {
		const filename = `${ font.prefix }-${ weight }-normal.woff2`;
		const src = path.join( filesDir, filename );
		const dest = path.join( FONTS_OUT, filename );

		if ( ! fs.existsSync( src ) ) {
			throw new Error(
				`No se encontró ${ src }. ¿Está instalado ${ font.pkg }? Corré npm install.`
			);
		}

		fs.copyFileSync( src, dest );

		css += `@font-face {\n`;
		css += `\tfont-family: '${ font.family }';\n`;
		css += `\tfont-style: normal;\n`;
		css += `\tfont-weight: ${ weight };\n`;
		css += `\tfont-display: swap;\n`;
		css += `\tsrc: url('../fonts/${ filename }') format('woff2');\n`;
		css += `}\n\n`;
	}
}

fs.mkdirSync( path.dirname( CSS_OUT ), { recursive: true } );
fs.writeFileSync( CSS_OUT, css );

console.log( `Fuentes copiadas a ${ FONTS_OUT }` );
console.log( `CSS generado en ${ CSS_OUT }` );
