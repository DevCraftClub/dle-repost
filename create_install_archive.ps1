param(
	[Parameter(Mandatory = $true)]
	[string]$ManifestPath
)

$ErrorActionPreference = 'Stop'

$cyrillic = @{
	'а' = 'a'; 'б' = 'b'; 'в' = 'v'; 'г' = 'g'; 'д' = 'd'; 'е' = 'e'; 'ё' = 'yo'
	'ж' = 'zh'; 'з' = 'z'; 'и' = 'i'; 'й' = 'y'; 'к' = 'k'; 'л' = 'l'; 'м' = 'm'
	'н' = 'n'; 'о' = 'o'; 'п' = 'p'; 'р' = 'r'; 'с' = 's'; 'т' = 't'; 'у' = 'u'
	'ф' = 'f'; 'х' = 'h'; 'ц' = 'ts'; 'ч' = 'ch'; 'ш' = 'sh'; 'щ' = 'sch'
	'ъ' = ''; 'ы' = 'y'; 'ь' = ''; 'э' = 'e'; 'ю' = 'yu'; 'я' = 'ya'
}

function Convert-TransliteratedText {
	param([string]$Text)

	$builder = New-Object System.Text.StringBuilder
	foreach ($char in $Text.ToCharArray()) {
		$lower = [string]$char
		$lower = $lower.ToLowerInvariant()
		if ($cyrillic.ContainsKey($lower)) {
			[void]$builder.Append($cyrillic[$lower])
		}
		else {
			[void]$builder.Append($char)
		}
	}
	return $builder.ToString()
}

function Convert-Slug {
	param([string]$Name)

	$text = (Convert-TransliteratedText -Text $Name).ToLowerInvariant()
	$text = [regex]::Replace($text, '[^a-z0-9]+', '_')
	$text = [regex]::Replace($text, '_+', '_').Trim('_')
	if ([string]::IsNullOrWhiteSpace($text)) {
		return 'plugin'
	}
	return $text
}

function Convert-SafeVersion {
	param([string]$Version)

	return [regex]::Replace($Version, '[^a-zA-Z0-9._-]+', '_')
}

$manifest = Get-Content -Path $ManifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
if (-not $manifest.name -or -not $manifest.version) {
	throw 'manifest.json must contain name and version'
}

$slug = Convert-Slug -Name $manifest.name
$version = Convert-SafeVersion -Version $manifest.version
Write-Output ("install_{0}_{1}.zip" -f $slug, $version)
