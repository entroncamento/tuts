$root = "C:\tuts-workspace\tuts-core"

$excludeDirs = @(
    ".git",
    "node_modules",
    "vendor",
    "storage\logs",
    "storage\framework",
    "bootstrap\cache",
    "public\build",
    "public\hot",
    ".vite",
    ".vscode",
    ".idea"
)

$excludeFiles = @(
    ".env",
    ".env.local",
    "*.log",
    "*.sqlite",
    "package-lock.json"
)

function Should-ExcludeDir($path) {
    $relative = $path.Replace($root, "").TrimStart("\")

    foreach ($exclude in $excludeDirs) {
        if ($relative -eq $exclude -or $relative.StartsWith("$exclude\")) {
            return $true
        }
    }

    return $false
}

function Should-ExcludeFile($file) {
    foreach ($pattern in $excludeFiles) {
        if ($file.Name -like $pattern) {
            return $true
        }
    }

    return $false
}

function Print-Tree($path, $prefix = "") {
    $items = Get-ChildItem $path -Force |
        Where-Object {
            if ($_.PSIsContainer) {
                -not (Should-ExcludeDir $_.FullName)
            } else {
                -not (Should-ExcludeFile $_)
            }
        } |
        Sort-Object @{Expression = "PSIsContainer"; Descending = $true}, Name

    for ($i = 0; $i -lt $items.Count; $i++) {
        $item = $items[$i]
        $isLast = $i -eq $items.Count - 1

        $connector = if ($isLast) { "\-- " } else { "+-- " }
        "$prefix$connector$($item.Name)"

        if ($item.PSIsContainer) {
            $nextPrefix = if ($isLast) { "$prefix    " } else { "$prefix|   " }
            Print-Tree $item.FullName $nextPrefix
        }
    }
}

$output = @()
$output += "tuts-core/"
$output += Print-Tree $root

$output | Out-File "$root\tree_tuts_core.txt" -Encoding UTF8

Write-Host "Tree criada em: $root\tree_tuts_core.txt"