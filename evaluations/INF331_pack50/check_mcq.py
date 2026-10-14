import data_mcq as d
bad = 0
for i, q in enumerate(d.M, 1):
    L = [len(o) for o in q['opts']]
    rank = sorted(L).index(L[0]) + 1          # 1 = shortest, 4 = longest
    ratio = max(L) / min(L)
    flag = '' if (ratio <= 1.07 or max(L) - min(L) <= 3) and rank in (2, 3) else '  <-- fix'
    bad += bool(flag)
    print(f"Q{i:02d} {q['lang']} {L} ratio {ratio:.2f} correct is #{rank} of 4 by length{flag}")
print('to fix:', bad)
