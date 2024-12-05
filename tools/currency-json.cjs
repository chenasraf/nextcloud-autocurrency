#!/usr/bin/env node
/* eslint-disable no-undef */

function findIntersections(json1, json2) {
  const json1Currencies = new Set(Object.keys(json2.usd).map((code) => code.toLowerCase()))

  const filteredCurrencies = Object.entries(json1).reduce((filtered, [key, value]) => {
    if (json1Currencies.has(key.toLowerCase())) {
      filtered[key] = value
    }
    return filtered
  }, {})

  const sortedCurrencies = Object.keys(filteredCurrencies)
    .sort()
    .reduce((sorted, key) => {
      sorted[key] = filteredCurrencies[key]
      return sorted
    }, {})

  return sortedCurrencies
}

async function main() {
  const base = 'usd'
  const apibase = `https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/${base}.json`

  console.log(`Fetching data from ${apibase}...`)
  const response = await fetch(apibase)
  const rates = await response.json()
  console.log(`Fetched ${Object.keys(rates.usd).length} currencies`)

  // eslint-disable-next-line @typescript-eslint/no-require-imports
  const symbols = require('../lib/Service/symbols.json')
  const finalJson = findIntersections(symbols, rates)

  console.log(JSON.stringify(finalJson, null, 2))
}

main()
