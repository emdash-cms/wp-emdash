// Runs `changeset version`, then copies the new version and its changeset
// summaries into the places WordPress reads: the plugin header, the version
// constant, and the readme's Stable tag and Changelog.

import { execFileSync } from "node:child_process";
import { readdirSync, readFileSync, writeFileSync } from "node:fs";

const PLUGIN_FILE = "plugins/emdash-exporter/emdash-exporter.php";
const README_FILE = "plugins/emdash-exporter/readme.txt";

// `changeset version` deletes the changeset files, so read the summaries first.
const summaries = readdirSync(".changeset")
	.filter((name) => name.endsWith(".md") && name !== "README.md")
	.sort()
	.map((name) => {
		const text = readFileSync(`.changeset/${name}`, "utf8").replace(/\r\n/g, "\n");
		const [, releases = "", summary = ""] = text.match(/^---\n([\s\S]*?)^---\n([\s\S]*)/m) ?? [];
		return releases.trim() ? summary.trim() : "";
	})
	.filter(Boolean);

execFileSync("pnpm", ["exec", "changeset", "version"], { stdio: "inherit" });

const { version } = JSON.parse(readFileSync("package.json", "utf8"));

function replaceOnce(file, pattern, replacement) {
	const text = readFileSync(file, "utf8");
	const matches = text.match(new RegExp(pattern.source, `${pattern.flags}g`));
	if (matches?.length !== 1) {
		throw new Error(`${file}: expected one match for ${pattern}, found ${matches?.length ?? 0}`);
	}
	writeFileSync(file, text.replace(pattern, replacement));
}

replaceOnce(PLUGIN_FILE, /^( \* Version: ).+$/m, `$1${version}`);
replaceOnce(
	PLUGIN_FILE,
	/^define\('EMDASH_EXPORTER_VERSION', '[^']*'\);$/m,
	`define('EMDASH_EXPORTER_VERSION', '${version}');`,
);
replaceOnce(README_FILE, /^([ \t]*Stable tag: ).+$/m, `$1${version}`);

const entries = summaries.map((summary) => `* ${summary.replace(/\n/g, "\n  ")}`).join("\n");
replaceOnce(
	README_FILE,
	/^== Changelog ==\n\n/m,
	() => `== Changelog ==\n\n= ${version} =\n\n${entries}\n\n`,
);
